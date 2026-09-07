<?php

namespace App\Http\Controllers\Consultant\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\Settings\StoreBlogPostRequest;
use App\Http\Requests\Consultant\Settings\UpdateBlogPostRequest;
use App\Models\BlogPost;
use App\Support\ConfigWriter;
use App\Support\SettingsTabs;
use App\Support\TenantUploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Blog settings tab: create/edit/delete posts (with or without a cover
 * picture) and flip the landing page's blog section between the config
 * placeholders and the tenant's real published posts.
 *
 * The landing switch writes `public.landing.blog.source` through
 * ConfigWriter, i.e. into the tenant's runtime layer — no deploy involved.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = BlogPost::query()
            ->when($request->query('status') === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($request->query('status') === 'published', fn ($q) => $q->published())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('consultant.settings.blog.index', [
            'posts' => $posts,
            'landingFromDatabase' => (string) site('public.landing.blog.source', 'config') === 'database',
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'blog',
        ]);
    }

    public function create(): View
    {
        return view('consultant.settings.blog.form', [
            'post' => new BlogPost(['status' => BlogPost::STATUS_DRAFT]),
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'blog',
        ]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $post = new BlogPost($data);
        $post->author_user_id = $request->user()->id;
        $post->published_at = $data['status'] === BlogPost::STATUS_PUBLISHED ? now() : null;

        if ($request->hasFile('cover')) {
            $post->cover_image_path = TenantUploads::store($request->file('cover'), 'blog');
        }

        $post->save();

        return redirect()
            ->route('consultant.settings.blog.index')
            ->with('success', 'نوشته ذخیره شد.');
    }

    public function edit(BlogPost $post): View
    {
        return view('consultant.settings.blog.form', [
            'post' => $post,
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'blog',
        ]);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $data = $request->validated();

        $post->fill($data);

        // Publishing for the first time stamps published_at; moving back to
        // draft leaves it (the history is useful, visibility is status-driven).
        if ($data['status'] === BlogPost::STATUS_PUBLISHED && ! $post->published_at) {
            $post->published_at = now();
        }

        if ($request->hasFile('cover')) {
            $post->cover_image_path = TenantUploads::store(
                $request->file('cover'),
                'blog',
                $post->cover_image_path,
            );
        } elseif ($request->boolean('remove_cover')) {
            TenantUploads::delete($post->cover_image_path);
            $post->cover_image_path = null;
        }

        $post->save();

        return redirect()
            ->route('consultant.settings.blog.index')
            ->with('success', 'نوشته به‌روزرسانی شد.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        TenantUploads::delete($post->cover_image_path);
        $post->delete();

        return redirect()
            ->route('consultant.settings.blog.index')
            ->with('success', 'نوشته حذف شد.');
    }

    /**
     * Toggle whether the landing page's blog section shows the tenant's real
     * posts or the config placeholders.
     */
    public function setLandingSource(Request $request): RedirectResponse
    {
        $request->validate([
            'source' => ['required', 'in:config,database'],
        ]);

        ConfigWriter::publishForTenant(
            tenant(),
            ['public.landing.blog.source' => $request->input('source')],
        );

        return back()->with('success', $request->input('source') === 'database'
            ? 'بخش وبلاگ صفحه اصلی از نوشته‌ها تغذیه می‌کند.'
            : 'بخش وبلاگ صفحه اصلی به حالت پیکربندی برگشت.');
    }
}
