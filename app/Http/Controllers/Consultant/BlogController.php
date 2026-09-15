<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\StoreBlogPostRequest;
use App\Http\Requests\Consultant\UpdateBlogPostRequest;
use App\Models\BlogPost;
use App\Support\TenantUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Blog management — the «وبلاگ» section of the main panel navigation,
 * alongside the dashboard. Create/edit/delete posts with a rich-text body
 * (blog-editor.js uploads inline images to media(), App\Support\RichText
 * allowlists what gets stored) and an optional cover picture; the public
 * landing page and blog pages feed entirely from the tenant's published
 * posts.
 */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $posts = BlogPost::query()
            ->when($request->query('status') === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($request->query('status') === 'published', fn ($q) => $q->published())
            // Drag-sorted first (reorder() keeps sort_order dense); fresh
            // posts all sit at 0 and surface newest-first via the id tiebreak.
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('consultant.blog.index', [
            'posts' => $posts,
        ]);
    }

    /**
     * The editor lives in a dialog on the blog list: when the route is
     * fetched with Accept: application/json (consultant-blog-modal.js) it
     * answers with the bare form fragment; as a normal navigation — deep
     * link or no-JS — it is still a complete page.
     */
    public function create(Request $request): View|HtmlResponse
    {
        return $this->form($request, new BlogPost(['status' => BlogPost::STATUS_DRAFT]));
    }

    private function form(Request $request, BlogPost $post): View|HtmlResponse
    {
        return $request->expectsJson()
            ? response()->view('consultant.blog.form', ['post' => $post])
            : view('consultant.blog.form-page', ['post' => $post]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        $post = new BlogPost($data);
        $post->author_user_id = $request->user()->id;
        $post->published_at = $data['status'] === BlogPost::STATUS_PUBLISHED ? now() : null;

        if ($request->hasFile('cover')) {
            $post->cover_image_path = TenantUploads::store($request->file('cover'), 'blog');
        }

        $post->save();

        if ($request->expectsJson()) {
            // The modal re-fetches the list afterwards, so the flash still
            // lands — nothing has followed a redirect to consume it.
            $request->session()->flash('success', 'نوشته ذخیره شد.');

            return response()->json(['ok' => true, 'id' => $post->id]);
        }

        return redirect()
            ->route('consultant.blog.index')
            ->with('success', 'نوشته ذخیره شد.');
    }

    public function edit(Request $request, BlogPost $post): View|HtmlResponse
    {
        return $this->form($request, $post);
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $post): RedirectResponse|JsonResponse
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

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'نوشته به‌روزرسانی شد.');

            return response()->json(['ok' => true, 'id' => $post->id]);
        }

        return redirect()
            ->route('consultant.blog.index')
            ->with('success', 'نوشته به‌روزرسانی شد.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        TenantUploads::delete($post->cover_image_path);
        $post->delete();

        return redirect()
            ->route('consultant.blog.index')
            ->with('success', 'نوشته حذف شد.');
    }

    /**
     * Inline image upload for the body editor (blog-editor.js). Files land
     * in the same tenant blog folder as covers; the response hands back the
     * absolute URL the editor inserts, which RichText::sanitize later
     * re-validates against the tenant tree on save.
     */
    public function media(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ], [
            'image.required' => 'ابتدا یک تصویر انتخاب کنید.',
            'image.image' => 'فایل انتخاب شده باید تصویر باشد.',
            'image.mimes' => 'تصویر باید JPG، PNG، WebP یا GIF باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۴ مگابایت باشد.',
        ]);

        $path = TenantUploads::store($data['image'], 'blog');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => tenant_asset($path),
        ]);
    }

    /**
     * Persist a drag-and-drop ordering from the blog list (blog-sort.js).
     *
     * The sent ids are whatever rows the list currently shows — possibly a
     * status-filtered slice — and they are permuted across exactly the global
     * slots they already occupy, so posts outside the slice keep their places.
     * The whole set is then renumbered densely (0..N-1), which folds the
     * all-zero "never sorted yet" ties into the explicit new order.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'posts' => ['required', 'array', 'min:2'],
            'posts.*' => ['integer', 'distinct'],
        ]);

        $sent = array_map('intval', $validated['posts']);

        $all = BlogPost::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $byId = $all->keyBy('id');

        // Drop ids the tenant does not own (deleted mid-drag, or forged).
        $wanted = array_flip(array_values(array_filter($sent, fn (int $id) => $byId->has($id))));
        $queue = array_values(array_filter($sent, fn (int $id) => isset($wanted[$id])));

        if (count($queue) < 2) {
            abort(422, 'چیزی برای مرتب‌سازی وجود ندارد.');
        }

        DB::transaction(function () use ($all, $byId, $wanted, $queue) {
            $cursor = 0;

            foreach ($all as $position => $post) {
                if (isset($wanted[$post->id])) {
                    $post = $byId[$queue[$cursor++]];
                }

                if ($post->sort_order !== $position) {
                    $post->sort_order = $position;
                    $post->save();
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'ترتیب نوشته‌ها ذخیره شد.');
    }
}
