<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\View\View;

/**
 * Public blog pages, tenant-themed through the same landing shell.
 *
 * Only published posts are ever reachable; the explicit published() filter
 * (rather than route model binding) keeps drafts out of reach of a guessed
 * slug. Tenant isolation is enforced by the BlogPost global scope, which
 * resolves from the request domain.
 */
class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()
            // Same drag order the consultant curates (reorder() keeps
            // sort_order dense); fresh zero-tied posts fall back to newest.
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9);

        return view('public.blog.index', [
            'posts' => $posts,
            'blogCfg' => site('public.landing.blog', []),
        ]);
    }

    public function show(string $slug): View
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('public.blog.show', [
            'post' => $post,
            'blogCfg' => site('public.landing.blog', []),
        ]);
    }
}
