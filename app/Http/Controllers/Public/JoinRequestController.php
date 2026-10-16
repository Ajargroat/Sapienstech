<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Rules\IranianPhone;
use App\Rules\NationalCode;
use App\Support\Plans;
use App\Support\StudentEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The public side of a join request.
 *
 * A visitor on the tenant's own website posts their details; the row lands in
 * `student_join_requests` and waits for a tenant admin/owner. The tenant is
 * resolved from the host by IdentifyTenant — there is deliberately no tenant
 * field in the form, so a submission cannot be aimed at another tenant's site.
 *
 * The endpoint is public by necessity (the visitor has no account yet), so it
 * is throttled and the validation is strict: this is the only place an
 * unauthenticated person can create a row.
 */
class JoinRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = tenant();

        // No tenant means no roster to join. IdentifyTenant already 404s an
        // unknown host, so this only guards the dev fallback host resolving to
        // nothing — see PageController::shared().
        abort_unless($tenant, 404);

        // Feature switch, resolved per tenant through site() like every other
        // consultant/student feature gate.
        abort_unless((bool) site('features.student_join_requests', false), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Email is optional now; the phone is the primary identity.
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40', new IranianPhone],
            'national_code' => ['nullable', 'string', 'max:20', new NationalCode],
            'grade' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'major' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
            // The visitor names a PLAN, never an amount. Whitelisted against
            // the tenant's configured plans below, so an unknown slug is
            // dropped rather than stored.
            'plan_slug' => ['nullable', 'string', 'max:40'],
        ]);

        // The amount is the TENANT's price, never the visitor's input: the
        // visitor only declares *which plan* they paid for (and the reference).
        // Taking the number from the form would let anyone name their own
        // price, and the consultant would have nothing to verify it against.
        //
        // The price is resolved from the configured plan list by slug, so a
        // slug the tenant does not offer resolves to null and falls back to
        // the section's own `amount` (which is itself a hint, not a charge).
        $planSlug = (string) ($data['plan_slug'] ?? '');
        $planPrice = Plans::priceFor($planSlug);
        $plan = $planPrice === null ? null : (Plans::options()[$planSlug] ?? null);

        $data['plan_slug'] = $plan === null ? null : $planSlug;
        // Denormalized so a later rename in config cannot rewrite history.
        $data['plan_name'] = $plan === null ? null : trim((string) ($plan['name'] ?? ''));
        $data['amount'] = $planPrice
            ?? (int) (site('public.landing.join.amount') ?? 0);

        // An email already on the active roster raises a ValidationException
        // from the service, which redirects back with a field error — the
        // framework handles that, so there is nothing to catch here.
        StudentEnrollment::submit($tenant, $data);

        $message = site('public.landing.join.success_message')
            ?: 'درخواست شما ثبت شد. پس از تأیید پرداخت، حساب کاربری شما فعال می‌شود.';

        return back()->with('join_status', $message);
    }
}
