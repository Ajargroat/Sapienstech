<?php

namespace Tests\Feature\Consultant;

use App\Models\BlogPost;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebsiteConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Blog settings tab + public blog pages + the landing source switch.
 */
class BlogSettingsTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_blog_tab_lists_posts(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'title' => 'نوشتهٔ نمونه',
        ]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/blog")
            ->assertOk()
            ->assertSee('نوشتهٔ نمونه');
    }

    public function test_post_can_be_created_as_draft_and_published(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->post("http://{$host}/consultant/settings/blog", [
            'title' => 'عنوان جدید',
            'excerpt' => 'چکیده',
            'body' => 'متن کامل نوشته',
            'status' => 'draft',
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);

        // Publish it via update.
        $this->actingAs($user)->patch("http://{$host}/consultant/settings/blog/{$post->id}", [
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

        $this->actingAs($user)->post("http://{$host}/consultant/settings/blog", [
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

        $this->actingAs($user)->post("http://{$host}/consultant/settings/blog", [
            'title' => 'با تصویر',
            'status' => 'draft',
            'cover' => UploadedFile::fake()->create('cover.jpg', 5, 'image/jpeg'),
        ])->assertRedirect();

        $post = BlogPost::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotNull($post->cover_image_path);
        $this->assertFileExists(public_path('tenants/'.$tenant->slug.'/'.$post->cover_image_path));
    }

    public function test_landing_source_switch_writes_the_runtime_layer(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->put("http://{$host}/consultant/settings/blog/landing", [
            'source' => 'database',
        ])->assertRedirect();

        $row = WebsiteConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame('database', data_get($row->layout_config, 'public.landing.blog.source'));
    }

    public function test_landing_shows_published_posts_when_source_is_database(): void
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

        site_override(['public' => ['landing' => ['blog' => ['source' => 'database']]]]);

        $html = $this->get("http://{$host}/")->assertOk()->getContent();

        $this->assertStringContainsString('مقالهٔ منتشرشدهٔ لندینگ', $html);
        $this->assertStringNotContainsString('پیش‌نویسِ پنهان', $html);
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
            ->get("http://{$hostB}/consultant/settings/blog")
            ->assertOk()
            ->assertDontSee('مخصوص مجموعه الف');

        $this->actingAs($userB)
            ->get("http://{$hostB}/consultant/settings/blog/{$postA->id}/edit")
            ->assertNotFound();
    }

    public function test_blog_tab_hidden_when_feature_disabled(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        site_override(['features' => ['blog_management' => false]]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/blog")
            ->assertNotFound();
    }
}
