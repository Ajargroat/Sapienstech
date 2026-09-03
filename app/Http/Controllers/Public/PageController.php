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
        // Landing page is fully driven by config('consultant.public') via the
        // ViewServiceProvider composer.
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
     * Data every public page needs. Note: `config` may be null —
     * your website_configs table is empty right now, and the views
     * are written to handle that with defaults.
     */
    private function shared(): array
    {
        $tenant = tenant();

        return [
            'tenant' => $tenant,
            'config' => WebsiteConfig::where('tenant_id', $tenant->id)->first(),
        ];
    }
}
