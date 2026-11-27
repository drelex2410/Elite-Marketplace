<?php

namespace App\Services\Shops;

use App\Models\Shop;
use App\Models\User;
use App\Notifications\DB\SellerRegistrationNotification as SellerRegistrationDatabaseNotification;
use App\Notifications\SellerRegistrationNotification;
use App\Services\Auth\AuthCodeService;
use App\Services\Auth\AuthSettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShopRegistrationService
{
    public function __construct(
        private readonly AuthSettingsService $authSettingsService,
        private readonly AuthCodeService $authCodeService
    ) {}

    public function register(array $payload): array
    {
        $phone = $this->normalizePhone($payload['phone']);

        try {
            [$user, $shop] = DB::transaction(function () use ($payload, $phone): array {
                $existing = User::query()
                    ->where('phone', $phone)
                    ->orWhere('email', Str::lower($payload['email']))
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new HttpException(422, 'User already exists with this email or phone.');
                }

                $user = new User;
                $user->name = trim($payload['name']);
                $user->email = Str::lower(trim($payload['email']));
                $user->phone = $phone;
                $user->user_type = 'seller';
                $user->password = Hash::make($payload['password']);
                $user->email_verified_at = null;
                $user->banned = false;
                $user->save();

                $shop = new Shop;
                $shop->user_id = $user->id;
                $shop->approval = 0;
                $shop->published = 0;
                $shop->verification_status = 0;
                $shop->name = trim($payload['shopName']);
                $shop->slug = $this->uniqueSlug($payload['shopName']);
                $shop->phone = $this->normalizePhone($payload['shopPhone']);
                $shop->address = trim($payload['shopAddress']);
                if (Schema::hasColumn('shops', 'status')) {
                    $shop->status = 'pending';
                }
                $shop->save();

                $user->shop_id = $shop->id;
                $user->save();

                return [$user, $shop];
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw new HttpException(422, 'User already exists with this email or phone.', $exception);
            }

            throw $exception;
        }

        $this->sendVerificationWhenRequired($user);
        $this->notifyAdministrators($shop);

        return [
            'success' => true,
            'message' => translate('Thanks for registering your shop.'),
            'data' => [
                'application_id' => $shop->id,
                'shop_slug' => $shop->slug,
                'status' => 'pending',
                'requires_email_verification' => $user->email_verified_at === null,
            ],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '-') ?: 'shop';
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

    private function sendVerificationWhenRequired(User $user): void
    {
        if (! $this->authSettingsService->verificationEnabled()) {
            return;
        }

        try {
            $channel = $this->authSettingsService->verificationChannel() ?? 'email';
            $target = $channel === 'phone' ? (string) $user->phone : (string) $user->email;
            $code = $this->authCodeService->issueVerificationCode($user, $channel, $target);
            $this->authCodeService->sendVerificationCode($user, $channel, $target, $code->code);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function notifyAdministrators(Shop $shop): void
    {
        try {
            $administrators = User::query()
                ->whereIn('user_type', ['admin', 'staff'])
                ->where('banned', 0)
                ->get();

            if ($administrators->isEmpty()) {
                return;
            }

            Notification::send($administrators, new SellerRegistrationDatabaseNotification($shop));
            Notification::send($administrators, new SellerRegistrationNotification($shop));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
