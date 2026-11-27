<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shops') && Schema::hasColumn('shops', 'status')) {
            DB::table('shops')
                ->whereNull('status')
                ->orderBy('id')
                ->chunkById(500, function ($shops): void {
                    foreach ($shops as $shop) {
                        $status = (int) $shop->approval !== 1
                            ? 'pending'
                            : ((int) $shop->published === 1 ? 'active' : 'inactive');

                        DB::table('shops')->where('id', $shop->id)->update(['status' => $status]);
                    }
                });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'review_status')) {
            DB::table('products')
                ->whereNull('review_status')
                ->orderBy('id')
                ->chunkById(500, function ($products): void {
                    foreach ($products as $product) {
                        $status = (int) $product->approved === 1
                            ? 'approved'
                            : ((int) $product->published === 1 ? 'pending_approval' : 'draft');

                        DB::table('products')->where('id', $product->id)->update(['review_status' => $status]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Data backfills are intentionally retained on rollback.
    }
};
