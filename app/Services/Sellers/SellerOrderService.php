<?php

namespace App\Services\Sellers;

use App\Models\Order;
use App\Models\OrderUpdate;
use App\Models\Shop;
use App\Notifications\DB\OrderNotification;
use App\Services\Orders\OrderCompletionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SellerOrderService
{
    private const TRANSITIONS = [
        'order_placed' => ['confirmed'],
        'confirmed' => ['processed', 'ready_for_pickup'],
        'processed' => ['shipped'],
        'shipped' => ['delivered'],
        'ready_for_pickup' => ['picked_up'],
        'picked_up' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function paginate(Shop $shop, array $filters = [], bool $pickupOnly = false): LengthAwarePaginator
    {
        return $this->ownedQuery($shop)
            ->with([
                'combined_order',
                'user',
                'orderDetails' => function ($query) use ($shop): void {
                    $query->whereHas('product', fn ($product) => $product->where('shop_id', $shop->id));
                },
            ])
            ->when($pickupOnly, fn (Builder $query) => $query->where('type_of_delivery', 'pickup'))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = '%'.trim((string) $filters['search']).'%';
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('code', 'like', $search)
                        ->orWhereHas('combined_order', fn (Builder $combined) => $combined->where('code', 'like', $search));
                });
            })
            ->when(filled($filters['payment_status'] ?? null), fn (Builder $query) => $query->where('payment_status', $filters['payment_status']))
            ->when(filled($filters['delivery_status'] ?? null), fn (Builder $query) => $query->where('delivery_status', $filters['delivery_status']))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForShop(Shop $shop, int $orderId): Order
    {
        $order = $this->ownedQuery($shop)->findOrFail($orderId);

        $order->load([
            'user',
            'shop',
            'combined_order',
            'order_udpates',
            'orderDetails' => function ($query) use ($shop): void {
                $query->whereHas('product', fn ($product) => $product->where('shop_id', $shop->id))
                    ->with(['product', 'variation.combinations']);
            },
        ]);

        abort_if($order->orderDetails->isEmpty(), 404);

        $order->setAttribute('seller_items_total', $order->orderDetails->sum('total'));

        return $order;
    }

    public function transition(Shop $shop, int $orderId, string $nextStatus, int $actorId): Order
    {
        return DB::transaction(function () use ($shop, $orderId, $nextStatus, $actorId): Order {
            $order = Order::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($orderId);
            $this->assertNoForeignItems($order, $shop);
            $allowed = $this->allowedTransitions($order);

            if (! in_array($nextStatus, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => translate('That fulfillment transition is not allowed.'),
                ]);
            }

            $from = (string) $order->delivery_status;
            $order->delivery_status = $nextStatus;
            app(OrderCompletionService::class)->sync($order, $nextStatus);
            $order->save();

            OrderUpdate::query()->create([
                'order_id' => $order->id,
                'user_id' => $actorId,
                'note' => "Seller fulfillment status changed from {$from} to {$nextStatus}.",
            ]);

            DB::afterCommit(function () use ($order): void {
                try {
                    if ($order->user) {
                        Notification::send($order->user, new OrderNotification($order));
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            return $order->refresh();
        }, 3);
    }

    public function updateTracking(Shop $shop, int $orderId, array $tracking, int $actorId): Order
    {
        return DB::transaction(function () use ($shop, $orderId, $tracking, $actorId): Order {
            $order = Order::query()->where('shop_id', $shop->id)->lockForUpdate()->findOrFail($orderId);
            $this->assertNoForeignItems($order, $shop);

            abort_if(in_array($order->delivery_status, ['delivered', 'cancelled'], true), 422);

            $order->courier_name = $tracking['courier_name'];
            $order->tracking_number = $tracking['tracking_number'];
            $order->tracking_url = $tracking['tracking_url'] ?? null;
            $order->save();

            OrderUpdate::query()->create([
                'order_id' => $order->id,
                'user_id' => $actorId,
                'note' => 'Seller updated courier tracking information.',
            ]);

            return $order->refresh();
        }, 3);
    }

    public function allowedTransitions(Order $order): array
    {
        $allowed = self::TRANSITIONS[(string) $order->delivery_status] ?? [];

        if ($order->type_of_delivery === 'pickup') {
            return array_values(array_intersect($allowed, ['confirmed', 'ready_for_pickup', 'picked_up', 'delivered']));
        }

        return array_values(array_diff($allowed, ['ready_for_pickup', 'picked_up']));
    }

    public function allowedTransitionsForShop(Order $order, Shop $shop): array
    {
        if ((int) $order->shop_id !== (int) $shop->id || $this->hasForeignItems($order, $shop)) {
            return [];
        }

        return $this->allowedTransitions($order);
    }

    private function ownedQuery(Shop $shop): Builder
    {
        return Order::query()->where(function (Builder $query) use ($shop): void {
            $query->where('shop_id', $shop->id)
                ->orWhereHas('orderDetails.product', fn (Builder $product) => $product->where('shop_id', $shop->id));
        });
    }

    private function assertNoForeignItems(Order $order, Shop $shop): void
    {
        abort_if($this->hasForeignItems($order, $shop), 403);
    }

    private function hasForeignItems(Order $order, Shop $shop): bool
    {
        return $order->orderDetails()
            ->whereHas('product', fn ($product) => $product->where('shop_id', '!=', $shop->id))
            ->exists();
    }
}
