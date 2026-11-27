<?php

namespace App\Services\Sellers;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\SellerInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SellerManagementService
{
    public function __construct(private readonly SellerAuditService $auditService) {}

    public function create(array $payload): User
    {
        $plainPassword = $payload['password'] ?? Str::password(32);
        $inviteToReset = empty($payload['password']);

        $seller = DB::transaction(function () use ($payload, $plainPassword): User {
            $duplicate = User::query()
                ->where('email', Str::lower($payload['email']))
                ->orWhere('phone', $this->normalizePhone($payload['phone']))
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new HttpException(422, 'A user already exists with this email or phone.');
            }

            $seller = new User;
            $seller->name = trim($payload['name']);
            $seller->email = Str::lower(trim($payload['email']));
            $seller->phone = $this->normalizePhone($payload['phone']);
            $seller->password = Hash::make($plainPassword);
            $seller->user_type = 'seller';
            $seller->email_verified_at = ! empty($payload['email_verified']) ? now() : null;
            $seller->banned = false;
            $seller->save();

            $status = ShopStatus::from($payload['status'] ?? ShopStatus::Pending->value);
            $shop = new Shop;
            $shop->user_id = $seller->id;
            $shop->name = trim($payload['shop_name']);
            $shop->slug = $this->uniqueSlug($payload['shop_name']);
            $shop->phone = $this->normalizePhone($payload['shop_phone']);
            $shop->address = trim($payload['shop_address']);
            $shop->meta_description = $payload['shop_description'] ?? null;
            $shop->logo = $payload['shop_logo'] ?? null;
            $shop->banners = $payload['shop_banner'] ?? null;
            $shop->status = $status;
            $shop->verification_status = (bool) ($payload['verification_status'] ?? false);
            $shop->approval = in_array($status, [ShopStatus::Approved, ShopStatus::Active, ShopStatus::Inactive, ShopStatus::Suspended], true);
            $shop->published = $status === ShopStatus::Active;
            $shop->seller_package_id = $payload['seller_package_id'] ?? null;
            $shop->commission = $payload['commission'] ?? 0;
            if ($shop->approval) {
                $shop->approved_at = now();
                $shop->approved_by = auth()->id();
            }
            $shop->save();

            $seller->shop_id = $shop->id;
            $seller->save();

            $this->auditService->record(
                'seller.created',
                $seller,
                $shop,
                $seller,
                [],
                ['email' => $seller->email, 'status' => $status->value]
            );

            return $seller->load('shop');
        }, 3);

        try {
            $seller->notify(new SellerInvitationNotification($inviteToReset));
            if ($inviteToReset) {
                Password::sendResetLink(['email' => $seller->email]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $seller;
    }

    public function update(User $seller, array $payload): User
    {
        return DB::transaction(function () use ($seller, $payload): User {
            $seller = User::query()->where('user_type', 'seller')->lockForUpdate()->findOrFail($seller->id);
            $shop = Shop::query()->lockForUpdate()->findOrFail($seller->shop_id);
            $before = ['name' => $seller->name, 'email' => $seller->email, 'phone' => $seller->phone, 'shop_name' => $shop->name];

            $seller->name = trim($payload['name']);
            $seller->email = Str::lower(trim($payload['email']));
            $seller->phone = $this->normalizePhone($payload['phone']);
            if (! empty($payload['password'])) {
                $seller->password = Hash::make($payload['password']);
            }
            $seller->save();

            $shop->name = trim($payload['shop_name']);
            $shop->phone = $this->normalizePhone($payload['shop_phone']);
            $shop->address = trim($payload['shop_address']);
            $shop->meta_description = $payload['shop_description'] ?? null;
            $shop->logo = $payload['shop_logo'] ?? $shop->logo;
            $shop->banners = $payload['shop_banner'] ?? $shop->banners;
            $shop->seller_package_id = $payload['seller_package_id'] ?? null;
            $shop->commission = $payload['commission'] ?? $shop->commission;
            $shop->save();

            $this->auditService->record(
                'seller.updated',
                $seller,
                $shop,
                $seller,
                $before,
                ['name' => $seller->name, 'email' => $seller->email, 'phone' => $seller->phone, 'shop_name' => $shop->name]
            );

            return $seller->load('shop');
        }, 3);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $counter = 2;
        while (Shop::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[\s()-]+/', '', trim($phone)) ?: trim($phone);
    }
}
