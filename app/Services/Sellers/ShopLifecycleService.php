<?php

namespace App\Services\Sellers;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopLifecycleService
{
    public function __construct(private readonly SellerAuditService $auditService) {}

    public function transition(Shop $shop, ShopStatus $status, User $actor, ?string $reason = null): Shop
    {
        return DB::transaction(function () use ($shop, $status, $actor, $reason): Shop {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $before = $this->snapshot($shop);

            if (in_array($status, [ShopStatus::Suspended, ShopStatus::Rejected], true) && blank($reason)) {
                throw ValidationException::withMessages([
                    'reason' => translate('A reason is required for this status.'),
                ]);
            }

            $shop->status = $status;
            $shop->status_reason = $reason;

            match ($status) {
                ShopStatus::Pending => $this->setVisibility($shop, false, false),
                ShopStatus::Approved => $this->approve($shop, $actor, false),
                ShopStatus::Active => $this->approve($shop, $actor, true),
                ShopStatus::Inactive => $this->setVisibility($shop, true, false),
                ShopStatus::Suspended => $this->suspend($shop, $actor),
                ShopStatus::Rejected => $this->setVisibility($shop, false, false),
            };

            $shop->save();
            Cache::forget('published_shops_ids');

            $this->auditService->record(
                'shop.status.changed',
                $shop->user,
                $shop,
                $shop,
                $before,
                $this->snapshot($shop),
                $reason
            );

            return $shop->refresh();
        }, 3);
    }

    public function setVerification(Shop $shop, bool $verified, User $actor, ?string $reason = null): Shop
    {
        return DB::transaction(function () use ($shop, $verified, $reason): Shop {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $before = $this->snapshot($shop);
            $shop->verification_status = $verified;
            if (! $verified && $shop->status === ShopStatus::Active) {
                $shop->published = false;
                $shop->status = ShopStatus::Approved;
            }
            $shop->save();
            Cache::forget('published_shops_ids');

            $this->auditService->record(
                $verified ? 'shop.verification.approved' : 'shop.verification.rejected',
                $shop->user,
                $shop,
                $shop,
                $before,
                $this->snapshot($shop),
                $reason
            );

            return $shop->refresh();
        }, 3);
    }

    private function approve(Shop $shop, User $actor, bool $publish): void
    {
        $shop->approval = true;
        $shop->published = $publish;
        $shop->approved_at ??= now();
        $shop->approved_by ??= $actor->id;
        $shop->suspended_at = null;
        $shop->suspended_by = null;
    }

    private function suspend(Shop $shop, User $actor): void
    {
        $shop->published = false;
        $shop->suspended_at = now();
        $shop->suspended_by = $actor->id;
    }

    private function setVisibility(Shop $shop, bool $approved, bool $published): void
    {
        $shop->approval = $approved;
        $shop->published = $published;
        if ($shop->status !== ShopStatus::Suspended) {
            $shop->suspended_at = null;
            $shop->suspended_by = null;
        }
    }

    private function snapshot(Shop $shop): array
    {
        return [
            'status' => $shop->status instanceof ShopStatus ? $shop->status->value : $shop->status,
            'approval' => (bool) $shop->approval,
            'published' => (bool) $shop->published,
            'verification_status' => (bool) $shop->verification_status,
            'status_reason' => $shop->status_reason,
        ];
    }
}
