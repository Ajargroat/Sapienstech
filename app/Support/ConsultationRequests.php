<?php

namespace App\Support;

use App\Mail\ConsultationRequestReceived;
use App\Models\ConsultationRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The one place a consultation request is written.
 *
 * Mirrors the shape of StudentEnrollment minus what consultation does not
 * have: no payment to declare, no roster seat to create, no reviewer to
 * stamp. What it keeps is the transaction-with-a-locked-row discipline —
 * two rapid submissions from the same address cannot queue a duplicate
 * pending row, and approve/reject cannot race into a mixed state.
 *
 * Tenant identity always comes from the resolved tenant (the request's
 * host) — never from client input. The staff notification is queued with a
 * data-only payload, so the worker never needs a tenant binding and no
 * model snapshot can be serialized into the queue.
 */
final class ConsultationRequests
{
    /**
     * Record a visitor's consultation request, or refresh the one already
     * open for that email on this tenant.
     *
     * Re-submitting updates the same pending row rather than queueing a
     * duplicate — the consultant must not puzzle over three copies of one
     * visitor. The email match is case-insensitive, like open_key was for
     * join requests.
     *
     * @param  array<string, mixed>  $data
     */
    public static function submit(Tenant $tenant, array $data): ConsultationRequest
    {
        $attributes = [
            'tenant_id' => $tenant->id,
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'phone' => self::nullable($data['phone'] ?? null),
            'message' => trim((string) $data['message']),
        ];

        $request = DB::transaction(function () use ($tenant, $attributes) {
            $existing = ConsultationRequest::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', ConsultationRequest::STATUS_PENDING)
                ->whereRaw('lower(email) = ?', [$attributes['email']])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return ConsultationRequest::withoutGlobalScopes()->create($attributes + [
                'status' => ConsultationRequest::STATUS_PENDING,
            ]);
        });

        // Notify only on a brand-new row: a duplicate submission already had
        // its email delivered, and re-sending would spam the tenant's staff.
        if ($request->wasRecentlyCreated) {
            self::notifyStaff($tenant, $request);
        }

        return $request;
    }

    /** Approve: a pure status transition, no enrollment behind it. */
    public static function approve(ConsultationRequest $request): ConsultationRequest
    {
        return self::decide($request, ConsultationRequest::STATUS_APPROVED);
    }

    /** Reject: a pure status transition; the row stays for the record. */
    public static function reject(ConsultationRequest $request): ConsultationRequest
    {
        return self::decide($request, ConsultationRequest::STATUS_REJECTED);
    }

    /**
     * Idempotent decide: whatever the row's current status, it re-pins to
     * exactly `$status` — a double click cannot error out or drift.
     */
    private static function decide(ConsultationRequest $request, string $status): ConsultationRequest
    {
        return DB::transaction(function () use ($request, $status) {
            $locked = ConsultationRequest::withoutGlobalScopes()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update(['status' => $status]);

            return $locked;
        });
    }

    /**
     * Queue one mailable per tenant User. Recipients are resolved HERE, in
     * the request, while the tenant is live — the mailable only ever
     * carries scalars (name, email, phone, message, tenant PK, tenant
     * display name), never a model snapshot.
     */
    private static function notifyStaff(Tenant $tenant, ConsultationRequest $request): void
    {
        // Explicit tenant_id predicate rather than the BelongsToTenant
        // global scope alone: correct even when the current context has no
        // tenant bound (a direct service call), and never Students.
        $recipients = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->all();

        if ($recipients === []) {
            // No staff to tell: the row is the record of truth. Silent skip.
            return;
        }

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new ConsultationRequestReceived(
                name: $request->name,
                email: $request->email,
                phone: $request->phone,
                message: $request->message,
                tenantId: (int) $tenant->id,
                tenantName: (string) $tenant->name,
            ));
        }
    }

    /** Trim to null — a blank optional field stays a true NULL. */
    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
