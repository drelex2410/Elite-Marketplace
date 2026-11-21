<?php

namespace App\Services\Sellers;

use App\Models\SellerPayout;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\PayoutStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerPayoutService
{
    public function __construct(private readonly SellerAuditService $auditService) {}

    public function request(Shop $shop, string $amount, ?string $note = null): SellerPayout
    {
        $amountMinor = $this->toMinorUnits($amount);
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => translate('Enter a valid payout amount.')]);
        }

        return DB::transaction(function () use ($shop, $amountMinor, $note): SellerPayout {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            $reserved = SellerPayout::query()
                ->where('shop_id', $shop->id)
                ->whereIn('status', ['requested', 'pending', 'approved', 'processing'])
                ->sum('requested_amount');
            $availableMinor = $this->toMinorUnits((string) $shop->current_balance)
                - $this->toMinorUnits((string) $reserved);

            if ($amountMinor > $availableMinor) {
                throw ValidationException::withMessages([
                    'amount' => translate('The payout amount exceeds your available unreserved balance.'),
                ]);
            }

            $payout = new SellerPayout;
            $payout->shop_id = $shop->id;
            $payout->status = 'requested';
            $payout->requested_amount = $this->fromMinorUnits($amountMinor);
            $payout->paid_amount = 0;
            $payout->seller_note = $note;
            $payout->save();

            return $payout->refresh();
        }, 3);
    }

    public function cancel(SellerPayout $payout, Shop $shop): SellerPayout
    {
        abort_unless((int) $payout->shop_id === (int) $shop->id, 403);

        return DB::transaction(function () use ($payout): SellerPayout {
            $payout = SellerPayout::query()->lockForUpdate()->findOrFail($payout->id);
            if (! in_array($payout->status, ['requested', 'pending'], true)) {
                throw ValidationException::withMessages([
                    'payout' => translate('Only pending payout requests can be cancelled.'),
                ]);
            }
            $payout->status = 'cancelled';
            $payout->save();

            return $payout->refresh();
        }, 3);
    }

    public function settle(SellerPayout $payout, User $actor, array $settlement): SellerPayout
    {
        return DB::transaction(function () use ($payout, $settlement): SellerPayout {
            $payout = SellerPayout::query()->with('shop.user')->lockForUpdate()->findOrFail($payout->id);
            $shop = Shop::query()->lockForUpdate()->findOrFail($payout->shop_id);

            if (! in_array($payout->status, ['requested', 'pending', 'approved', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'payout' => translate('This payout has already reached a final state.'),
                ]);
            }

            if (SellerPayout::query()->where('txn_code', $settlement['txn_code'])->where('id', '!=', $payout->id)->exists()) {
                throw ValidationException::withMessages([
                    'txn_code' => translate('This settlement reference has already been used.'),
                ]);
            }

            $amountMinor = $this->toMinorUnits((string) $payout->requested_amount);
            $balanceMinor = $this->toMinorUnits((string) $shop->current_balance);
            if ($amountMinor > $balanceMinor) {
                throw ValidationException::withMessages([
                    'payout' => translate('The shop balance is no longer sufficient for this payout.'),
                ]);
            }

            $before = $payout->only(['status', 'paid_amount', 'payment_method', 'txn_code']);
            $payout->status = 'paid';
            $payout->paid_amount = $this->fromMinorUnits($amountMinor);
            $payout->payment_method = $settlement['payment_method'];
            $payout->payment_details = $settlement['payment_details'] ?? null;
            $payout->txn_code = $settlement['txn_code'];
            $payout->save();

            $shop->current_balance = $this->fromMinorUnits($balanceMinor - $amountMinor);
            $shop->save();

            $this->auditService->record(
                'payout.settled',
                $shop->user,
                $shop,
                $payout,
                $before,
                $payout->only(['status', 'paid_amount', 'payment_method', 'txn_code']),
                $settlement['payment_details'] ?? null
            );

            DB::afterCommit(function () use ($shop, $payout): void {
                try {
                    $shop->user?->notify(new PayoutStatusNotification($payout));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

            return $payout->refresh();
        }, 3);
    }

    private function toMinorUnits(string $amount): int
    {
        $normalized = trim(str_replace(',', '', $amount));
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw ValidationException::withMessages(['amount' => translate('Use a monetary amount with at most two decimal places.')]);
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$minor : $minor;
    }

    private function fromMinorUnits(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
