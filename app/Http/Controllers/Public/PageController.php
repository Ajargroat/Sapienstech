<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Feature;
use App\Models\WebsiteConfig;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('public.home', array_merge($this->shared(), [
            'features' => Feature::where('enabled', true)->pluck('key'),
            'content'  => Content::all()->keyBy('key'),
        ]));
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
