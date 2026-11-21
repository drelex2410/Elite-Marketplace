<?php

namespace Tests\Feature\Seller;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sellers\SellerOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SellerOrderIsolationTest extends TestCase
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

    public function test_mixed_order_only_exposes_items_owned_by_each_shop(): void
    {
        [, $shopA] = $this->createSeller('a');
        [, $shopB] = $this->createSeller('b');
        $productA = $this->createProduct($shopA, 'Product A');
        $productB = $this->createProduct($shopB, 'Product B');
        $order = $this->createOrder($shopA);
        $this->createDetail($order, $productA, 100);
        $this->createDetail($order, $productB, 250);

        $service = app(SellerOrderService::class);
        $forA = $service->findForShop($shopA, $order->id);
        $forB = $service->findForShop($shopB, $order->id);

        $this->assertSame([$productA->id], $forA->orderDetails->pluck('product_id')->all());
        $this->assertSame(100.0, (float) $forA->seller_items_total);
        $this->assertSame([$productB->id], $forB->orderDetails->pluck('product_id')->all());
        $this->assertSame(250.0, (float) $forB->seller_items_total);
        $this->assertSame([], $service->allowedTransitionsForShop($forA, $shopA));
        $this->assertSame([], $service->allowedTransitionsForShop($forB, $shopB));
    }

    public function test_unrelated_seller_cannot_access_order(): void
    {
        [, $shopA] = $this->createSeller('a');
        [, $shopB] = $this->createSeller('b');
        $product = $this->createProduct($shopA, 'Product A');
        $order = $this->createOrder($shopA);
        $this->createDetail($order, $product, 100);

        $this->expectException(ModelNotFoundException::class);
        app(SellerOrderService::class)->findForShop($shopB, $order->id);
    }

    public function test_seller_can_only_move_normalized_order_forward_one_state(): void
    {
        Notification::fake();
        [$seller, $shop] = $this->createSeller('a');
        $product = $this->createProduct($shop, 'Product A');
        $order = $this->createOrder($shop);
        $this->createDetail($order, $product, 100);

        $service = app(SellerOrderService::class);
        $updated = $service->transition($shop, $order->id, 'confirmed', $seller->id);

        $this->assertSame('confirmed', $updated->delivery_status);
        $this->assertDatabaseHas('order_updates', [
            'order_id' => $order->id,
            'user_id' => $seller->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->transition($shop, $order->id, 'delivered', $seller->id);
    }

    public function test_mixed_order_fulfillment_cannot_modify_other_seller_items(): void
    {
        [$sellerA, $shopA] = $this->createSeller('a');
        [, $shopB] = $this->createSeller('b');
        $order = $this->createOrder($shopA);
        $this->createDetail($order, $this->createProduct($shopA, 'Product A'), 100);
        $this->createDetail($order, $this->createProduct($shopB, 'Product B'), 200);

        $this->expectException(HttpException::class);
        app(SellerOrderService::class)->transition($shopA, $order->id, 'confirmed', $sellerA->id);
    }

    private function createSeller(string $suffix): array
    {
        $user = User::query()->create([
            'name' => 'Seller '.strtoupper($suffix),
            'email' => "seller-{$suffix}@example.com",
            'password' => bcrypt('secret123'),
            'user_type' => 'seller',
        ]);
        $shopId = DB::table('shops')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Shop '.strtoupper($suffix),
            'slug' => "shop-{$suffix}",
            'approval' => 1,
            'published' => 1,
            'verification_status' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->shop_id = $shopId;
        $user->save();

        return [$user->refresh(), Shop::query()->findOrFail($shopId)];
    }

    private function createProduct(Shop $shop, string $name): Product
    {
        return Product::query()->create([
            'shop_id' => $shop->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'published' => true,
            'approved' => true,
        ]);
    }

    private function createOrder(Shop $shop): Order
    {
        return Order::query()->create([
            'shop_id' => $shop->id,
            'code' => 'ORDER-'.str()->random(8),
            'delivery_status' => 'order_placed',
            'payment_status' => 'paid',
            'grand_total' => 350,
            'shipping_address' => json_encode(['name' => 'Customer', 'address' => '1 Test Street']),
        ]);
    }

    private function createDetail(Order $order, Product $product, float $total): void
    {
        DB::table('order_details')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => $total,
            'tax' => 0,
            'total' => $total,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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
            $table->timestamp('email_verified_at')->nullable();
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
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('published')->default(false);
            $table->boolean('approved')->default(false);
            $table->timestamps();
        });
        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('lang')->nullable();
            $table->string('name')->nullable();
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('product_taxes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax', 15, 2)->default(0);
            $table->string('tax_type')->nullable();
            $table->timestamps();
        });
        Schema::create('combined_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('combined_order_id')->nullable();
            $table->string('code')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('type_of_delivery')->nullable();
            $table->string('delivery_status');
            $table->string('payment_status');
            $table->decimal('grand_total', 20, 2)->default(0);
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('tracking_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->decimal('price', 20, 2)->default(0);
            $table->decimal('tax', 20, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
        Schema::create('order_updates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->text('note');
            $table->timestamps();
        });
    }
}
