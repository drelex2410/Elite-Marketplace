<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seller_packages')) {
            Schema::table('seller_packages', function (Blueprint $table): void {
                if (! Schema::hasColumn('seller_packages', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                if (! Schema::hasColumn('seller_packages', 'currency')) {
                    $table->string('currency', 3)->nullable()->after('amount');
                }
                if (! Schema::hasColumn('seller_packages', 'max_active_products')) {
                    $table->unsignedInteger('max_active_products')->nullable()->after('product_upload_limit');
                }
                if (! Schema::hasColumn('seller_packages', 'features')) {
                    $table->json('features')->nullable()->after('duration');
                }
                if (! Schema::hasColumn('seller_packages', 'status')) {
                    $table->boolean('status')->default(true)->index()->after('features');
                }
                if (! Schema::hasColumn('seller_packages', 'display_order')) {
                    $table->unsignedInteger('display_order')->default(0)->after('status');
                }
                if (! Schema::hasColumn('seller_packages', 'renewal_settings')) {
                    $table->json('renewal_settings')->nullable()->after('display_order');
                }
            });
        }

        if (Schema::hasTable('seller_package_payments')) {
            Schema::table('seller_package_payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('seller_package_payments', 'idempotency_key')) {
                    $table->string('idempotency_key', 100)->nullable()->unique()->after('transaction_id');
                }
                if (! Schema::hasColumn('seller_package_payments', 'activated_at')) {
                    $table->timestamp('activated_at')->nullable()->after('idempotency_key');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seller_package_payments')) {
            Schema::table('seller_package_payments', function (Blueprint $table): void {
                $columns = array_values(array_filter(
                    ['idempotency_key', 'activated_at'],
                    fn (string $column): bool => Schema::hasColumn('seller_package_payments', $column)
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('seller_packages')) {
            Schema::table('seller_packages', function (Blueprint $table): void {
                $columns = array_values(array_filter([
                    'description',
                    'currency',
                    'max_active_products',
                    'features',
                    'status',
                    'display_order',
                    'renewal_settings',
                ], fn (string $column): bool => Schema::hasColumn('seller_packages', $column)));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
