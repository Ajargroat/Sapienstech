<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\WebsiteConfig;
use App\Support\LandingSurface;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(LandingSurface $surface): View
    {
        // A tenant may own a bespoke landing page (resources/landings/{slug}/).
        // LandingSurface fails closed, so `null` here means "no published
        // bespoke page" and the shared skeleton below renders exactly as it
        // always has. The legacy path is byte-identical either way.
        if ($bespoke = $surface->for(tenant())) {
            return view($bespoke);
        }

        // Landing page is fully driven by the resolved site config
        // (public.landing.*) via the ViewServiceProvider composer.
        return view('public.landing');
    }

    /**
     * Staff-only draft preview of a bespoke landing surface.
     *
     * Renders with `force: true`, so an unpublished page (no meta.json, or
     * `"enabled": false`) is visible to its author before it goes live. The
     * `auth` gate is the web guard, so students and guests never reach it; it
     * is never linked from a public page. The `*.landing` composer applies
     * unchanged (the view name is what it matches on), and X-Robots-Tag keeps
     * the draft out of indexes even if the URL leaks.
     */
    public function preview(LandingSurface $surface): Response
    {
        $bespoke = $surface->for(tenant(), true);

        abort_if($bespoke === null, 404);

        return response()
            ->view($bespoke)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function about(): View
    {
        return view('public.about', array_merge($this->shared(), [
            'content' => Content::where('key', 'about')->first(),
        ]));
    }

    public function contact(): View
    {
        return view('public.contact', array_merge($this->shared(), [
            'content' => Content::where('key', 'contact')->first(),
        ]));
    }

    /**
     * Data every public page needs.
     *
     * `tenant` may be null: IdentifyTenant only 404s on an *unknown* host, and
     * the dev fallback host is allowed to resolve to nothing. Callers must
     * therefore null-check rather than assume a tenant exists.
     */
    private function shared(): array
    {
        $tenant = tenant();

        return [
            'tenant' => $tenant,
            'config' => $tenant
                ? WebsiteConfig::where('tenant_id', $tenant->id)->first()
                : null,
        ];
    }
}
