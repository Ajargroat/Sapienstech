<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\ConsultationRequest;
use App\Support\ConsultationRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «درخواست مشاوره» — the queue of visitors asking to be contacted back.
 *
 * A visitor fills the tenant's public consultation form; this page is where
 * the tenant decides. There is nothing to approve *into*: approving or
 * rejecting is a pure status transition (App\Support\ConsultationRequests)
 * and the row stays as the record of truth either way.
 *
 * Mirrors JoinRequestController's review queue — same tabs, same counts,
 * same decision endpoints — minus the payment fields and the student a join
 * request would create, which consultation does not have. Only the tenant
 * admin/owner decides (staff can see what is waiting), enforced server-side
 * like the join queue.
 */
class ConsultationRequestController extends Controller
{
    /** The review queue's tabs. */
    public const STATUS_FILTERS = ['pending', 'approved', 'rejected', 'all'];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', ConsultationRequest::STATUS_PENDING);

        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = ConsultationRequest::STATUS_PENDING;
        }

        $query = ConsultationRequest::query()->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($matches) use ($q) {
                $matches->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        // Counts for the tabs, always tenant-wide and independent of the
        // active filter, so the numbers do not move as the reviewer pages
        // through. The BelongsToTenant scope keeps these inside the tenant
        // resolved from the request's host.
        $counts = [
            'pending' => ConsultationRequest::query()->pending()->count(),
            'approved' => ConsultationRequest::query()->where('status', ConsultationRequest::STATUS_APPROVED)->count(),
            'rejected' => ConsultationRequest::query()->where('status', ConsultationRequest::STATUS_REJECTED)->count(),
        ];
        $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];

        return view('consultant.consultations.index', [
            'requests' => $query->paginate(15)->withQueryString(),
            'counts' => $counts,
            'filters' => ['status' => $status, 'q' => (string) $request->query('q', '')],
            'canManage' => $request->user()->isTenantAdmin(),
        ]);
    }

    /**
     * One request in full, so the reviewer can read what the visitor wrote
     * before deciding.
     */
    public function show(ConsultationRequest $consultationRequest): View
    {
        // Bindings resolve before the tenant middleware, so the owning tenant
        // is verified here (the same guard the join endpoints use).
        abort_unless(tenant() && (int) $consultationRequest->tenant_id === (int) tenant()->id, 404);

        return view('consultant.consultations.show', [
            'request' => $consultationRequest,
            'canManage' => request()->user()->isTenantAdmin(),
        ]);
    }

    /**
     * Approve: a pure status transition, no enrollment behind it.
     */
    public function approve(Request $request, ConsultationRequest $consultationRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() && (int) $consultationRequest->tenant_id === (int) tenant()->id, 404);

        // Idempotent by construction: the service re-pins the status, so a
        // double click cannot error out or drift.
        ConsultationRequests::approve($consultationRequest);

        return redirect()
            ->route('consultant.consultations.show', $consultationRequest)
            ->with('status', 'درخواست مشاوره تأیید شد.');
    }

    public function reject(Request $request, ConsultationRequest $consultationRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() && (int) $consultationRequest->tenant_id === (int) tenant()->id, 404);

        ConsultationRequests::reject($consultationRequest);

        return redirect()
            ->route('consultant.consultations.index')
            ->with('status', 'درخواست مشاوره رد شد.');
    }
}
