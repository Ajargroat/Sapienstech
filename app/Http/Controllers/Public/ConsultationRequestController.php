<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreConsultationRequest;
use App\Support\ConsultationRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * The public side of a consultation request.
 *
 * A visitor on the tenant's own website posts their details; the row lands
 * in `consultation_requests` and waits for a tenant admin/owner. The tenant
 * is resolved from the host by IdentifyTenant — deliberately no tenant
 * field in the form, so a submission cannot be aimed at another tenant's
 * site (the /join idiom). There is no payment and nothing becomes a roster
 * row: the row is one-way, and the staff email rides the queue.
 */
class ConsultationRequestController extends Controller
{
    public function store(StoreConsultationRequest $form): RedirectResponse|JsonResponse
    {
        $tenant = tenant();

        // No tenant means no staff to answer it. IdentifyTenant already 404s
        // an unknown host; this only guards the dev fallback host resolving
        // to nothing — see PageController::shared().
        abort_unless($tenant, 404);

        // Feature switch, resolved per tenant through site() like every
        // other consultant/student feature gate.
        abort_unless((bool) site('features.consultation_requests', false), 404);

        ConsultationRequests::submit($tenant, $form->validated());

        $message = 'درخواست مشاوره شما ثبت شد. به‌زودی با شما تماس می‌گیریم.';

        return $form->expectsJson()
            ? response()->json(['status' => 'ok', 'message' => $message])
            : back()->with('consultation_status', $message);
    }
}
