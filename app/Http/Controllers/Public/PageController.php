<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\WebsiteConfig;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        // Landing page is fully driven by the resolved site config
        // (public.landing.*) via the ViewServiceProvider composer.
        return view('public.landing');
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
