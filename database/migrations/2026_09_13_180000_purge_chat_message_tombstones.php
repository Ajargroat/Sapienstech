<?php

use App\Models\Tenant;
use App\Support\TenantUploads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chat deletion became a real deletion (no "این پیام حذف شد" tombstones).
 * Nothing filters chat_messages.deleted_at anymore, so the rows soft-deleted
 * under the old behaviour must be purged — otherwise they would resurface
 * with their original bodies. Their stored attachments are removed too.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        $tombstones = DB::table('chat_messages')
            ->whereNotNull('deleted_at')
            ->get(['id', 'tenant_id', 'attachment_path']);

        if ($tombstones->isEmpty()) {
            return;
        }

        DB::table('chat_messages')
            ->whereIn('id', $tombstones->pluck('id')->all())
            ->delete();

        // TenantUploads::delete resolves the path under the tenant's own
        // public/tenants/{slug} tree, so bind each tenant before unlinking.
        foreach ($tombstones->whereNotNull('attachment_path')->groupBy('tenant_id') as $tenantId => $rows) {
            $tenant = Tenant::find($tenantId);

            if (! $tenant) {
                continue;
            }

            app()->instance('tenant', $tenant);

            foreach ($rows as $row) {
                TenantUploads::delete($row->attachment_path);
            }
        }
    }

    public function down(): void
    {
        // Tombstones are unrecoverable — nothing to rebuild.
    }
};
