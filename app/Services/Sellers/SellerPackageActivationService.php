<?php

namespace App\Services\Sellers;

use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerPackageActivationService
{
    public function activate(
        User $seller,
        SellerPackage $package,
        string $idempotencyKey,
        string $paymentMethod,
        string $transactionId,
        ?array $paymentDetails = null,
        bool $offline = false
    ): SellerPackagePayment {
        if ($seller->user_type !== 'seller' || ! $seller->shop_id) {
            throw ValidationException::withMessages([
                'seller' => translate('A valid seller account is required.'),
            ]);
        }

        return DB::transaction(function () use (
            $seller,
            $package,
            $idempotencyKey,
            $paymentMethod,
            $transactionId,
            $paymentDetails,
            $offline
        ): SellerPackagePayment {
            $existing = SellerPackagePayment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing?->activated_at) {
                return $existing;
            }

            $shop = Shop::query()->lockForUpdate()->findOrFail($seller->shop_id);
            $payment = $existing ?: new SellerPackagePayment;
            $payment->user_id = $seller->id;
            $payment->seller_package_id = $package->id;
            $payment->amount = $package->amount;
            $payment->payment_method = $paymentMethod;
            $payment->payment_details = $paymentDetails ? json_encode($paymentDetails) : null;
            $payment->approval = 1;
            $payment->offline_payment = $offline;
            $payment->transaction_id = $transactionId;
            $payment->idempotency_key = $idempotencyKey;
            $payment->activated_at = now();
            $payment->save();

            $start = $shop->package_invalid_at && Carbon::parse($shop->package_invalid_at)->isFuture()
                ? Carbon::parse($shop->package_invalid_at)
                : now();

            $shop->seller_package_id = $package->id;
            $shop->product_upload_limit = $package->product_upload_limit;
            $shop->commission = $package->commission;
            $shop->package_invalid_at = $start->copy()->addDays((int) $package->duration)->toDateString();
            $shop->save();

            return $payment->refresh();
        }, 3);
    }

    public function createOfflinePending(
        User $seller,
        SellerPackage $package,
        string $transactionId,
        string $paymentMethod,
        ?string $receipt = null
    ): SellerPackagePayment {
        return DB::transaction(function () use ($seller, $package, $transactionId, $paymentMethod, $receipt): SellerPackagePayment {
            $payment = SellerPackagePayment::query()->firstOrNew([
                'transaction_id' => $transactionId,
                'user_id' => $seller->id,
            ]);
            $payment->seller_package_id = $package->id;
            $payment->amount = $package->amount;
            $payment->payment_method = $paymentMethod;
            $payment->approval = 0;
            $payment->offline_payment = 1;
            $payment->reciept = $receipt;
            $payment->save();

            return $payment->refresh();
        }, 3);
    }

    public function approveOffline(SellerPackagePayment $payment, User $actor): SellerPackagePayment
    {
        $seller = $payment->user;
        $package = $payment->seller_package;
        abort_unless($seller && $package, 422);
        $idempotencyKey = 'offline-package-payment:'.$payment->id;
        $payment->idempotency_key = $idempotencyKey;
        $payment->save();

        return $this->activate(
            $seller,
            $package,
            $idempotencyKey,
            (string) $payment->payment_method,
            (string) $payment->transaction_id,
            ['approved_by' => $actor->id],
            true
        );
    }
}
