<?php

namespace Tests\Feature\Seller;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSellerManagementTest extends TestCase
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

    public function test_admin_can_create_pending_seller_transactionally(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post('/admin/seller-store', [
            'name' => 'New Seller',
            'email' => 'new-seller@example.com',
            'phone' => '+2348000000001',
            'password' => 'Strongpass123',
            'password_confirmation' => 'Strongpass123',
            'shop_name' => 'New Seller Shop',
            'shop_phone' => '+2348000000002',
            'shop_address' => '12 Seller Street',
            'shop_description' => 'A seller shop',
            'status' => 'pending',
            'verification_status' => 0,
            'commission' => 5,
        ])->assertRedirect();

        $seller = User::query()->where('email', 'new-seller@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Strongpass123', $seller->password));
        $this->assertSame('seller', $seller->user_type);
        $this->assertNotNull($seller->shop_id);
        $this->assertDatabaseHas('shops', [
            'id' => $seller->shop_id,
            'status' => 'pending',
            'approval' => 0,
            'published' => 0,
            'verification_status' => 0,
        ]);
        $this->assertDatabaseHas('seller_admin_audits', [
            'actor_id' => $admin->id,
            'seller_id' => $seller->id,
            'action' => 'seller.created',
        ]);
    }

    public function test_admin_can_activate_and_suspend_shop_with_audit_history(): void
    {
        $admin = $this->createAdmin();
        $seller = $this->createSeller();

        $this->actingAs($admin)
            ->patch("/admin/sellers/{$seller->shop_id}/status", ['status' => 'active'])
            ->assertRedirect();

        $this->assertDatabaseHas('shops', [
            'id' => $seller->shop_id,
            'status' => 'active',
            'approval' => 1,
            'published' => 1,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/sellers/{$seller->shop_id}/status", [
                'status' => 'suspended',
                'reason' => 'Repeated policy violations',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shops', [
            'id' => $seller->shop_id,
            'status' => 'suspended',
            'published' => 0,
            'status_reason' => 'Repeated policy violations',
        ]);
        $this->assertSame(2, DB::table('seller_admin_audits')->where('action', 'shop.status.changed')->count());
    }

    private function createAdmin(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'user_type' => 'admin',
            'banned' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shopId = DB::table('shops')->insertGetId($this->shopPayload($id, 'Admin Shop', 'admin-shop', 'active'));
        DB::table('users')->where('id', $id)->update(['shop_id' => $shopId]);

        return User::query()->findOrFail($id);
    }

    private function createSeller(): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Seller',
            'email' => 'seller@example.com',
            'phone' => '+2348000000030',
            'password' => bcrypt('secret123'),
            'user_type' => 'seller',
            'banned' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shopId = DB::table('shops')->insertGetId($this->shopPayload($id, 'Seller Shop', 'seller-shop', 'pending'));
        DB::table('users')->where('id', $id)->update(['shop_id' => $shopId]);

        return User::query()->findOrFail($id);
    }

    private function shopPayload(int $userId, string $name, string $slug, string $status): array
    {
        return [
            'user_id' => $userId,
            'name' => $name,
            'slug' => $slug,
            'approval' => $status === 'active',
            'published' => $status === 'active',
            'verification_status' => $status === 'active',
            'status' => $status,
            'product_upload_limit' => 0,
            'commission' => 0,
            'current_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
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
            $table->unsignedBigInteger('seller_package_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedBigInteger('logo')->nullable();
            $table->string('banners')->nullable();
            $table->boolean('approval')->default(false);
            $table->boolean('published')->default(false);
            $table->boolean('verification_status')->default(false);
            $table->text('verification_info')->nullable();
            $table->string('status')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->unsignedBigInteger('suspended_by')->nullable();
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('current_balance', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('amount', 20, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->unsignedInteger('product_upload_limit')->default(0);
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
        Schema::create('uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
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
