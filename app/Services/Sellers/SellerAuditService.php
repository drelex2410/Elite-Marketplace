<?php

namespace App\Services\Sellers;

use App\Models\SellerAdminAudit;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SellerAuditService
{
    public function record(
        string $action,
        ?User $seller,
        ?Shop $shop,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?Request $request = null
    ): void {
        if (! Schema::hasTable('seller_admin_audits')) {
            return;
        }

        SellerAdminAudit::query()->create([
            'actor_id' => auth()->id(),
            'seller_id' => $seller?->id,
            'shop_id' => $shop?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'reason' => $reason,
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
        ]);
    }
}
