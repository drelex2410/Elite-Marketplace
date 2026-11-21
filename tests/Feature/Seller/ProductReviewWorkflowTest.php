<?php

namespace Tests\Feature\Seller;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\ProductReviewStatusNotification;
use App\Services\Sellers\ProductReviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductReviewWorkflowTest extends TestCase
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

    public function test_seller_submission_is_private_and_pending(): void
    {
        [$seller, $shop] = $this->createSeller();
        $product = $this->createProduct($shop, [
            'approved' => true,
            'published' => true,
            'review_status' => ProductReviewStatus::Approved->value,
        ]);

        $service = app(ProductReviewService::class);
        $service->prepareSellerSave($product, 'submit');
        $product->save();

        $product->refresh();
        $this->assertFalse($product->approved);
        $this->assertFalse($product->published);
        $this->assertSame(ProductReviewStatus::PendingApproval, $product->review_status);
        $this->assertNotNull($product->submitted_for_review_at);
        $this->assertSame($seller->shop_id, $product->shop_id);
    }

    public function test_admin_approval_is_audited_and_notifies_seller(): void
    {
        Notification::fake();
        [$seller, $shop] = $this->createSeller();
        $admin = $this->createAdmin();
        $product = $this->createProduct($shop, [
            'review_status' => ProductReviewStatus::PendingApproval->value,
            'submitted_for_review_at' => now(),
        ]);

        $this->actingAs($admin);
        $reviewed = app(ProductReviewService::class)->review(
            $product,
            ProductReviewStatus::Approved,
            $admin
        );

        $this->assertTrue($reviewed->approved);
        $this->assertTrue($reviewed->published);
        $this->assertSame(ProductReviewStatus::Approved, $reviewed->review_status);
        $this->assertDatabaseHas('seller_admin_audits', [
            'actor_id' => $admin->id,
            'seller_id' => $seller->id,
            'shop_id' => $shop->id,
            'subject_id' => $product->id,
            'action' => 'product.review.approved',
        ]);
        Notification::assertSentTo($seller, ProductReviewStatusNotification::class);
    }

    public function test_rejection_requires_a_reason(): void
    {
        [, $shop] = $this->createSeller();
        $admin = $this->createAdmin();
        $product = $this->createProduct($shop, [
            'review_status' => ProductReviewStatus::PendingApproval->value,
        ]);

        $this->expectException(ValidationException::class);
        app(ProductReviewService::class)->review(
            $product,
            ProductReviewStatus::Rejected,
            $admin
        );
    }

    private function createSeller(): array
    {
        $user = User::query()->create([
            'name' => 'Seller',
            'email' => 'seller@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'seller',
            'email_verified_at' => now(),
        ]);
        $shopId = DB::table('shops')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Seller Shop',
            'slug' => 'seller-shop',
            'approval' => true,
            'published' => true,
            'verification_status' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shop = Shop::query()->findOrFail($shopId);
        $user->shop_id = $shop->id;
        $user->save();

        return [$user->refresh(), $shop];
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function createProduct(Shop $shop, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'shop_id' => $shop->id,
            'name' => 'Review Product',
            'slug' => 'review-product',
            'approved' => false,
            'published' => false,
            'review_status' => ProductReviewStatus::Draft->value,
        ], $attributes));
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
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('current_balance', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('slug');
            $table->boolean('published')->default(false);
            $table->boolean('approved')->default(false);
            $table->string('review_status')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamp('submitted_for_review_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
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
