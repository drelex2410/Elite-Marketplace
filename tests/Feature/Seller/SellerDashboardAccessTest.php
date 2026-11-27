<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerDashboardAccessTest extends TestCase
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

    public function test_pending_seller_can_open_restricted_dashboard(): void
    {
        $seller = $this->createSeller(['approval' => 0, 'published' => 0, 'status' => 'pending']);

        $this->actingAs($seller)
            ->get('/seller')
            ->assertOk()
            ->assertSee('Application pending')
            ->assertSee('Seller dashboard');
    }

    public function test_banned_seller_cannot_open_dashboard(): void
    {
        $seller = $this->createSeller(['approval' => 1, 'published' => 1, 'status' => 'active'], true);

        $this->actingAs($seller)
            ->get('/seller')
            ->assertForbidden();
    }

    private function createSeller(array $shopAttributes, bool $banned = false): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Seller Example',
            'email' => 'seller@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'seller',
            'banned' => $banned,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shopId = DB::table('shops')->insertGetId(array_merge([
            'user_id' => $userId,
            'name' => 'Seller Shop',
            'slug' => 'seller-shop',
            'approval' => 0,
            'published' => 0,
            'verification_status' => 0,
            'status' => 'pending',
            'product_upload_limit' => 0,
            'commission' => 0,
            'current_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $shopAttributes));

        DB::table('users')->where('id', $userId)->update(['shop_id' => $shopId]);

        return User::query()->findOrFail($userId);
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
            $table->string('phone')->nullable();
            $table->string('user_type')->default('customer');
            $table->boolean('banned')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('logo')->nullable();
            $table->boolean('approval')->default(false);
            $table->boolean('published')->default(false);
            $table->boolean('verification_status')->default(false);
            $table->string('status')->nullable();
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('current_balance', 20, 2)->default(0);
            $table->date('package_invalid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->boolean('published')->default(false);
            $table->boolean('approved')->default(false);
            $table->string('review_status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('code')->nullable();
            $table->string('delivery_status')->default('order_placed');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->decimal('admin_commission', 20, 2)->default(0);
            $table->decimal('seller_earning', 20, 2)->default(0);
            $table->decimal('refund_amount', 20, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('status')->default('requested');
            $table->decimal('requested_amount', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('seller_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->unsignedInteger('duration')->default(0);
            $table->timestamps();
        });
    }
}
