<?php

namespace Tests\Feature\Seller;

use App\Models\SellerPackage;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sellers\SellerPackageActivationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerPackageActivationTest extends TestCase
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

    public function test_verified_activation_uses_package_values_and_is_idempotent(): void
    {
        [$seller, $shop] = $this->createSeller();
        $package = SellerPackage::query()->create([
            'name' => 'Premium',
            'amount' => '5000.00',
            'commission' => '5.00',
            'product_upload_limit' => 250,
            'duration' => 30,
            'status' => true,
        ]);
        $service = app(SellerPackageActivationService::class);

        $first = $service->activate($seller, $package, 'payment:1', 'paystack', 'PS-001');
        $expiry = $shop->fresh()->package_invalid_at->toDateString();
        $second = $service->activate($seller, $package, 'payment:1', 'paystack', 'PS-001');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('seller_package_payments')->count());
        $this->assertSame($package->id, $shop->fresh()->seller_package_id);
        $this->assertSame(250, $shop->fresh()->product_upload_limit);
        $this->assertSame('5.00', (string) $shop->fresh()->commission);
        $this->assertSame($expiry, $shop->fresh()->package_invalid_at->toDateString());
    }

    public function test_existing_future_expiry_is_extended(): void
    {
        [$seller, $shop] = $this->createSeller();
        $shop->package_invalid_at = today()->addDays(10);
        $shop->save();
        $package = SellerPackage::query()->create([
            'name' => 'Plus',
            'amount' => 100,
            'commission' => 3,
            'product_upload_limit' => 50,
            'duration' => 30,
            'status' => true,
        ]);

        app(SellerPackageActivationService::class)->activate(
            $seller,
            $package,
            'payment:2',
            'stripe',
            'ST-002'
        );

        $this->assertSame(today()->addDays(40)->toDateString(), $shop->fresh()->package_invalid_at->toDateString());
    }

    private function createSeller(): array
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
            'product_upload_limit' => 0,
            'commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seller->shop_id = $shopId;
        $seller->save();

        return [$seller->refresh(), Shop::query()->findOrFail($shopId)];
    }

    private function createSchema(): void
    {
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
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->boolean('approval')->default(false);
            $table->boolean('published')->default(false);
            $table->boolean('verification_status')->default(false);
            $table->string('status')->nullable();
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->date('package_invalid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 20, 2);
            $table->decimal('commission', 15, 2);
            $table->unsignedInteger('product_upload_limit');
            $table->unsignedInteger('duration');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('seller_package_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_package_id');
            $table->decimal('amount', 20, 2);
            $table->string('payment_method')->nullable();
            $table->text('payment_details')->nullable();
            $table->boolean('approval')->default(false);
            $table->boolean('offline_payment')->default(false);
            $table->string('reciept')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }
}
