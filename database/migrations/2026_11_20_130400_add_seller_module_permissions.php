<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'view_sellers',
        'create_sellers',
        'edit_sellers',
        'approve_sellers',
        'activate_sellers',
        'suspend_sellers',
        'verify_sellers',
        'view_seller_products',
        'approve_seller_products',
        'reject_seller_products',
        'manage_seller_products',
        'view_seller_packages',
        'create_seller_packages',
        'edit_seller_packages',
        'delete_seller_packages',
        'assign_seller_packages',
        'view_seller_orders',
        'view_seller_earnings',
        'view_seller_commissions',
        'manage_seller_payouts',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $guard = config('auth.defaults.guard', 'web');

        $permissions = collect($this->permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard])
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guard]);
        $role->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
