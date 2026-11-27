<?php

namespace Tests\Feature\Seller;

use App\Models\Shop;
use App\Models\User;
use App\Notifications\PayoutStatusNotification;
use App\Services\Sellers\SellerPayoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SellerPayoutWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
    }

    public function test_pending_requests_reserve_available_balance(): void
    {
        [, $shop] = $this->createSeller('100.00');
        $service = app(SellerPayoutService::class);
        $first = $service->request($shop, '60.25', 'First withdrawal');

        $this->assertSame('requested', $first->status);
        $this->assertSame('60.25', (string) $first->requested_amount);

        $this->expectException(ValidationException::class);
        $service->request($shop, '40.00');
    }

    public function test_authorized_settlement_is_atomic_audited_and_notified(): void
    {
        Notification::fake();
        [$seller, $shop] = $this->createSeller('100.00');
        $admin = $this->createAdmin();
        $payout = app(SellerPayoutService::class)->request($shop, '25.55');

        $this->actingAs($admin);
        $paid = app(SellerPayoutService::class)->settle($payout, $admin, [
            'payment_method' => 'bank_transfer',
            'txn_code' => 'SETTLEMENT-001',
            'payment_details' => 'Verified bank settlement',
        ]);

        $this->assertSame('paid', $paid->status);
        $this->assertSame('25.55', (string) $paid->paid_amount);
        $this->assertSame('74.45', (string) $shop->fresh()->current_balance);
        $this->assertDatabaseHas('seller_admin_audits', [
            'actor_id' => $admin->id,
            'seller_id' => $seller->id,
            'action' => 'payout.settled',
        ]);
        Notification::assertSentTo($seller, PayoutStatusNotification::class);

        $this->expectException(ValidationException::class);
        app(SellerPayoutService::class)->settle($paid, $admin, [
            'payment_method' => 'bank_transfer',
            'txn_code' => 'SETTLEMENT-002',
        ]);
    }

    public function test_settlement_reference_cannot_be_reused(): void
    {
        Notification::fake();
        [, $shop] = $this->createSeller('100.00');
        $admin = $this->createAdmin();
        $service = app(SellerPayoutService::class);
        $first = $service->request($shop, '20.00');
        $second = $service->request($shop, '20.00');
        $service->settle($first, $admin, [
            'payment_method' => 'bank_transfer',
            'txn_code' => 'UNIQUE-REF',
        ]);

        $this->expectException(ValidationException::class);
        $service->settle($second, $admin, [
            'payment_method' => 'bank_transfer',
            'txn_code' => 'UNIQUE-REF',
        ]);
    }

    private function createSeller(string $balance): array
    {
        $seller = User::query()->create([
            'name' => 'Seller',
            'email' => 'seller@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'seller',
        ]);
        $shopId = DB::table('shops')->insertGetId([
            'user_id' => $seller->id,
            'name' => 'Seller Shop',
            'slug' => 'seller-shop',
            'approval' => 1,
            'published' => 1,
            'verification_status' => 1,
            'status' => 'active',
            'current_balance' => $balance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seller->shop_id = $shopId;
        $seller->save();

        return [$seller->refresh(), Shop::query()->findOrFail($shopId)];
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('lang', 10);
            $table->string('lang_key');
            $table->text('lang_value')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('user_type')->default('customer');
            $table->boolean('banned')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('approval')->default(false);
            $table->boolean('published')->default(false);
            $table->boolean('verification_status')->default(false);
            $table->string('status')->nullable();
            $table->decimal('current_balance', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('status')->default('requested');
            $table->decimal('requested_amount', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->text('seller_note')->nullable();
            $table->text('payment_details')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('txn_code')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_admin_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }
}
