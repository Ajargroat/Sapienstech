<?php

namespace Tests\Feature\Consultant;

use App\Models\BlogPost;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blog management (top-nav section) + public blog pages + the landing
 * page's blog section, which is fed by the tenant's published posts.
 */
class BlogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create([
            'tenant_id'  => $tenant->id,
            'domain'     => $host,
            'is_primary' => true,
        ]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    private function consultantFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    public function test_blog_page_lists_posts(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'نوشتهٔ نمونه',
        ]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/blog")
            ->assertOk()
            ->assertSee('نوشتهٔ نمونه');
    }

    public function test_post_can_be_created_as_draft_and_published(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'عنوان جدید',
            'excerpt' => 'چکیده',
            'body' => 'متن کامل نوشته',
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);

        // Publish it via update.
        $this->actingAs($user)->patch("http://{$host}/consultant/blog/{$post->id}", [
            'title' => 'عنوان جدید',
            'status' => 'published',
        ])->assertRedirect();

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_post_without_picture_still_creates_and_renders(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'بدون تصویر',
            'status' => 'published',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($post->cover_image_path);

        $this->get("http://{$host}/blog/{$post->slug}")->assertOk()->assertSee('بدون تصویر');
    }

    public function test_cover_upload_is_stored_under_the_tenant_folder(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'با تصویر',
            'status' => 'draft',
            'cover' => UploadedFile::fake()->create('cover.jpg', 5, 'image/jpeg'),
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotNull($post->cover_image_path);
        $this->assertFileExists(public_path('tenants/'.$tenant->slug.'/'.$post->cover_image_path));
    }

    /* ------------------------------------------------------ rich-text body */

    public function test_body_is_stored_as_sanitized_rich_text(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $body = '<p>متنی <strong>پررنگ</strong> و <em>کج</em> با <u>زیرخط</u></p>'
            .'<script>alert(1)</script>'
            .'<img src="https://evil.example/x.png">'
            .'<p style="text-align:center">'
            .'<img src="https://app.test/tenants/'.$tenant->slug.'/blog/pic.jpg" class="align-center size-large hack" alt="نمونه">'
            .'</p>'
            .'<p><a href="javascript:alert(2)">پیوند</a> '
            .'<a href="https://example.ir">معتبر</a></p>';

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'بدنه غنی',
            'body' => $body,
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertStringContainsString('<strong>پررنگ</strong>', $post->body);
        $this->assertStringContainsString('<em>کج</em>', $post->body);
        $this->assertStringContainsString('<u>زیرخط</u>', $post->body);

        // Script tags, foreign images and javascript: links never survive.
        $this->assertStringNotContainsString('script', $post->body);
        $this->assertStringNotContainsString('alert(', $post->body);
        $this->assertStringNotContainsString('evil.example', $post->body);
        $this->assertStringNotContainsString('javascript:', $post->body);

        // The tenant's own media stays, with only the editor's vocabulary.
        $this->assertStringContainsString('tenants/'.$tenant->slug.'/blog/pic.jpg', $post->body);
        $this->assertStringContainsString('class="align-center size-large"', $post->body);
        $this->assertStringNotContainsString('hack', $post->body);
        $this->assertStringContainsString('text-align:center', $post->body);

        // External links are kept but fenced; javascript: sheds its href.
        $this->assertStringContainsString('rel="noopener noreferrer"', $post->body);
        $this->assertStringContainsString('href="https://example.ir"', $post->body);
    }

    public function test_plain_body_is_stored_as_paragraphs(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'ساده',
            'body' => "خط یکم\nخط دوم",
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('<p>خط یکم</p><p>خط دوم</p>', $post->body);
    }

    /**
     * Regression: raw contenteditable output wraps later lines in <div>s.
     * The sanitizer used to rebuild those with DOMDocument::renameNode(),
     * which does not exist in every PHP build — the save 500'd and the
     * dialog's error fallback made it look like the post simply vanished.
     */
    public function test_contenteditable_div_lines_survive_the_save(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'خط‌های div',
            'body' => 'خط اول<div>خط دوم</div>',
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('<p>خط اول</p><p>خط دوم</p>', $post->body);
    }

    public function test_tables_and_highlights_survive_sanitizing(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $body = '<p>پیش از <mark>هایلایت</mark> جدول</p>'
            .'<table><tbody><tr><th>سرستون</th><th>دوم</th></tr>'
            .'<tr><td colspan="2" width="500" bgcolor="red">سلول</td></tr></tbody></table>';

        $this->actingAs($user)->post("http://{$host}/consultant/blog", [
            'title' => 'جدول و هایلایت',
            'body' => $body,
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertStringContainsString('<mark>هایلایت</mark>', $post->body);
        $this->assertStringContainsString('<table><tbody><tr><th>سرستون</th>', $post->body);
        $this->assertStringContainsString('colspan="2"', $post->body);

        // Presentation attributes from pasted tables never ride along.
        $this->assertStringNotContainsString('bgcolor', $post->body);
        $this->assertStringNotContainsString('width=', $post->body);
    }

    public function test_form_ships_a_round_status_switch(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $fragment = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog/create", ['Accept' => 'application/json'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('blog-status-toggle', $fragment);
        $this->assertStringContainsString('name="status" value="draft"', $fragment);
        $this->assertStringContainsString('name="status" value="published"', $fragment);
        $this->assertStringNotContainsString('<select name="status"', $fragment);
    }

    public function test_rich_body_renders_as_html_on_the_public_post(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $post = BlogPost::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'body' => '<p>سلام</p><ul><li>یک</li><li>دو</li></ul>'
                .'<p style="text-align:center"><img src="/tenants/'.$tenant->slug.'/blog/a.jpg" class="align-center"></p>'
                .'<script>window.hacked=true</script>',
        ]);

        $html = $this->get("http://{$host}/blog/{$post->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('<li>یک</li>', $html);
        $this->assertStringContainsString('src="/tenants/'.$tenant->slug.'/blog/a.jpg"', $html);
        // Render-time filtering covers seeded/pre-editor rows too.
        $this->assertStringNotContainsString('hacked', $html);
    }

    public function test_legacy_plain_body_still_breaks_on_newlines(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $post = BlogPost::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'body' => "سطر یک\nسطر دو",
        ]);

        $html = $this->get("http://{$host}/blog/{$post->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('سطر یک<br', $html);
        $this->assertStringContainsString('سطر دو', $html);
    }

    public function test_edit_fragment_prefills_the_body_escaped_for_the_editor(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $post = BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'body' => '<p>غنی</p>',
        ]);

        $fragment = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog/{$post->id}/edit", ['Accept' => 'application/json'])
            ->assertOk()
            ->getContent();

        // The editor boots from the textarea, so the HTML rides escaped…
        $this->assertStringContainsString('&lt;p&gt;غنی&lt;/p&gt;', $fragment);
        // …and the editor chrome ships with the fragment.
        $this->assertStringContainsString('data-blog-editor', $fragment);
        $this->assertStringContainsString('data-editor-surface', $fragment);
    }

    public function test_media_endpoint_stores_inline_body_images(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $response = $this->actingAs($user)
            ->postJson("http://{$host}/consultant/blog/media", [
                'image' => UploadedFile::fake()->create('shot.png', 8, 'image/png'),
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $path = $response->json('path');
        $this->assertFileExists(public_path("tenants/{$tenant->slug}/{$path}"));
        $this->assertStringContainsString("/tenants/{$tenant->slug}/", $response->json('url'));

        $this->actingAs($user)
            ->postJson("http://{$host}/consultant/blog/media", [
                'image' => UploadedFile::fake()->create('notes.txt', 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_form_route_serves_a_bare_fragment_to_the_dialog(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $fragment = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog/create", ['Accept' => 'application/json'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<form', $fragment);
        $this->assertStringContainsString('name="cover"', $fragment);
        $this->assertStringNotContainsString('<html', $fragment);
        // The dialog owns its header, so the fragment carries no back link.
        $this->assertStringNotContainsString('بازگشت', $fragment);

        // Deep links and no-JS still get the complete page.
        $this->actingAs($user)
            ->get("http://{$host}/consultant/blog/create")
            ->assertOk()
            ->assertSee('بازگشت');
    }

    public function test_edit_fragment_prefills_the_existing_post(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $post = BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'برای ویرایش',
        ]);

        $fragment = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog/{$post->id}/edit", ['Accept' => 'application/json'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('برای ویرایش', $fragment);
        $this->assertStringContainsString('name="_method" value="PATCH"', $fragment);
        $this->assertStringNotContainsString('<html', $fragment);
    }

    public function test_dialog_save_reports_errors_as_json_and_flashes_success(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->postJson("http://{$host}/consultant/blog", [
            'status' => 'draft',
        ])->assertStatus(422)->assertJsonValidationErrors('title');

        $this->assertDatabaseMissing('blog_posts', ['tenant_id' => $tenant->id]);

        $this->actingAs($user)->postJson("http://{$host}/consultant/blog", [
            'title' => 'ذخیره از پنجره',
            'status' => 'draft',
        ])->assertOk()->assertJson(['ok' => true])->assertSessionHas('success');

        $this->assertDatabaseHas('blog_posts', [
            'tenant_id' => $tenant->id,
            'title' => 'ذخیره از پنجره',
        ]);
    }

    public function test_list_page_hosts_the_editor_dialog(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="blog-form-modal"', $html);
        $this->assertStringContainsString('data-blog-form', $html);
    }

    public function test_landing_shows_published_posts(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        BlogPost::factory()->published()->create([
            'tenant_id' => $tenant->id,
            'title' => 'مقالهٔ منتشرشدهٔ لندینگ',
        ]);
        BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'پیش‌نویسِ پنهان',
        ]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('مقالهٔ منتشرشدهٔ لندینگ', $html);
        $this->assertStringNotContainsString('پیش‌نویسِ پنهان', $html);
    }

    public function test_landing_omits_blog_section_without_published_posts(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'تنها یک پیش‌نویس',
        ]);

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertDontSee('آخرین مقالات', false);
    }

    public function test_drafts_are_not_publicly_reachable(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $draft = BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'پیش‌نویس',
        ]);

        $this->get("http://{$host}/blog/{$draft->slug}")->assertNotFound();
    }

    public function test_posts_are_isolated_between_tenants(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        [$tenantB, $hostB] = $this->tenantWithDomain();

        $userA = $this->consultantFor($tenantA);
        $userB = $this->consultantFor($tenantB);

        $postA = BlogPost::factory()->create([
            'tenant_id' => $tenantA->id,
            'title' => 'مخصوص مجموعه الف',
        ]);

        // Tenant B's consultant must not see or reach tenant A's post.
        $this->actingAs($userB)
            ->get("http://{$hostB}/consultant/blog")
            ->assertOk()
            ->assertDontSee('مخصوص مجموعه الف');

        $this->actingAs($userB)
            ->get("http://{$hostB}/consultant/blog/{$postA->id}/edit")
            ->assertNotFound();
    }

    public function test_drag_reorder_persists_a_dense_order_everywhere_follows(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $a = BlogPost::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'نوشتهٔ اول']);
        $b = BlogPost::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'نوشتهٔ دوم']);
        $c = BlogPost::factory()->published()->create(['tenant_id' => $tenant->id, 'title' => 'نوشتهٔ سوم']);

        // The drag sends the visible slice in its new order (here: oldest
        // first, flipping the newest-first default the fresh rows sit in).
        $this->actingAs($user)
            ->putJson("http://{$host}/consultant/blog/reorder", ['posts' => [$a->id, $b->id, $c->id]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, $a->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);

        $panel = $this->actingAs($user)
            ->get("http://{$host}/consultant/blog")->assertOk()->getContent();
        $this->assertLessThan(strpos($panel, 'نوشتهٔ دوم'), strpos($panel, 'نوشتهٔ اول'));
        $this->assertLessThan(strpos($panel, 'نوشتهٔ سوم'), strpos($panel, 'نوشتهٔ دوم'));

        $public = $this->get("http://{$host}/blog")->assertOk()->getContent();
        $this->assertLessThan(strpos($public, 'نوشتهٔ دوم'), strpos($public, 'نوشتهٔ اول'));
        $this->assertLessThan(strpos($public, 'نوشتهٔ سوم'), strpos($public, 'نوشتهٔ دوم'));
    }

    public function test_blog_section_hidden_when_feature_disabled(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        site_override(['features' => ['blog_management' => false]]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/blog")
            ->assertNotFound();
    }
}
