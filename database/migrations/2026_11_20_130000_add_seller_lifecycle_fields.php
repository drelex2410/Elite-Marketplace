<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table): void {
                if (! Schema::hasColumn('shops', 'status')) {
                    $table->string('status', 32)->nullable()->index()->after('published');
                }
                if (! Schema::hasColumn('shops', 'status_reason')) {
                    $table->text('status_reason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('shops', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('status_reason');
                }
                if (! Schema::hasColumn('shops', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->index()->after('approved_at');
                }
                if (! Schema::hasColumn('shops', 'suspended_at')) {
                    $table->timestamp('suspended_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('shops', 'suspended_by')) {
                    $table->unsignedBigInteger('suspended_by')->nullable()->index()->after('suspended_at');
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'review_status')) {
                    $table->string('review_status', 32)->nullable()->index()->after('approved');
                }
                if (! Schema::hasColumn('products', 'review_reason')) {
                    $table->text('review_reason')->nullable()->after('review_status');
                }
                if (! Schema::hasColumn('products', 'submitted_for_review_at')) {
                    $table->timestamp('submitted_for_review_at')->nullable()->after('review_reason');
                }
                if (! Schema::hasColumn('products', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('submitted_for_review_at');
                }
                if (! Schema::hasColumn('products', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable()->index()->after('reviewed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                $columns = array_values(array_filter([
                    'review_status',
                    'review_reason',
                    'submitted_for_review_at',
                    'reviewed_at',
                    'reviewed_by',
                ], fn (string $column): bool => Schema::hasColumn('products', $column)));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table): void {
                $columns = array_values(array_filter([
                    'status',
                    'status_reason',
                    'approved_at',
                    'approved_by',
                    'suspended_at',
                    'suspended_by',
                ], fn (string $column): bool => Schema::hasColumn('shops', $column)));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
