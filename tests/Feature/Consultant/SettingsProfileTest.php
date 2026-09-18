<?php

namespace Tests\Feature\Consultant;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The profile hub: the Telegram-style home (hero, action tiles, info rows,
 * settings list), its edit/appearance/chat sections on the one
 * profile route, the moved logout, feature gating, and the per-user
 * ("just for me") config layer applied by ApplyPersonalTheme.
 */
class SettingsProfileTest extends TestCase
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

        return User::factory()->consultant()->create([
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_profile_home_shows_the_identity_page_not_the_forms(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile")
            ->assertOk()
            ->assertSee('پروفایل')
            ->assertSee('تنظیمات')
            ->assertSee('ویرایش پروفایل')
            ->assertDontSee('tab=password', false)
            ->assertDontSee('class="profile-header"', false)
            ->assertSee('tab=appearance', false)
            ->assertSee($user->email)
            ->getContent();

        // The home page only shows identity data and routes to sections;
        // editing lives behind the settings-list rows (raw attribute
        // checks: assertSee escapes quotes by default).
        $this->assertStringNotContainsString('name="name"', $html);
        $this->assertStringNotContainsString('name="current_password"', $html);

        // Action tiles carry the main features the topnav knows.
        $this->assertStringContainsString('/consultant/direct-chat', $html);
    }

    public function test_edit_section_includes_the_password_dialog(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $edit = $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=edit")
            ->assertOk()
            ->assertSee($user->name)
            ->getContent();

        $this->assertStringContainsString('name="name"', $edit);
        $this->assertStringContainsString('name="bio"', $edit);
        foreach (['avatar', 'name', 'email', 'bio', 'password'] as $field) {
            $this->assertStringContainsString('data-profile-open="profile-dialog-'.$field.'"', $edit);
            $this->assertStringContainsString('<dialog class="profile-dialog" id="profile-dialog-'.$field.'"', $edit);
        }
        $this->assertStringNotContainsString('profile-edit-row-editor', $edit);
        $this->assertStringNotContainsString('profile-dialog-header', $edit);
        $this->assertStringNotContainsString('profile-dialog-close', $edit);
        $this->assertStringContainsString('aria-label="تصویر پروفایل"', $edit);
        $this->assertStringContainsString('aria-label="تغییر رمز عبور"', $edit);

        $this->assertStringContainsString('name="current_password"', $edit);
        $this->assertStringNotContainsString('data-profile-auto-open', $edit);
        $this->assertSame(1, substr_count($edit, 'data-profile-dialogs'));
        $this->assertSame(1, substr_count($edit, 'data-profile-open="profile-dialog-avatar"'));
        $this->assertStringContainsString('data-profile-avatar-toggle aria-expanded="false"', $edit);
        $this->assertStringNotContainsString('profile-edit-row-label">تصویر پروفایل', $edit);

        $user->forceFill(['avatar' => 'avatars/example.jpg'])->save();
        $this->get("http://{$host}/consultant/settings/profile?tab=edit")
            ->assertOk()
            ->assertSee('data-profile-avatar-delete hidden', false)
            ->assertSee('aria-label="حذف تصویر پروفایل"', false);

        $this->get("http://{$host}/consultant/settings/profile?tab=password")->assertNotFound();
    }

    public function test_invalid_profile_edit_reopens_its_dialog_with_the_attempted_value(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $url = "http://{$host}/consultant/settings/profile?tab=edit";

        $this->actingAs($user)->from($url)
            ->patch("http://{$host}/consultant/settings/profile", [
                'name' => $user->name,
                'email' => 'not-an-email',
                'bio' => $user->bio,
            ])->assertSessionHasErrors('email');

        $this->get($url)->assertOk()
            ->assertSee('aria-label="ایمیل (شناسهٔ حساب شما)"  data-profile-auto-open', false)
            ->assertSee('value="not-an-email"', false)
            ->assertSee('id="profile-error-email"', false);
    }

    public function test_unknown_tab_is_a_not_found(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=bogus")
            ->assertNotFound();
    }

    public function test_chat_section_loads_inside_the_profile_hub(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=chat")
            ->assertOk()
            ->assertSee('گفتگو')
            ->assertSee('profile-chat-section', false)
            ->assertSee('role="switch"', false)
            ->assertDontSee('data-chat-hint', false)
            ->assertDontSee('chat-setting-chat_read_receipts-help', false)
                        ->assertDontSee('فرستنده از خوانده‌شدن پیام مطلع شود.')
            ->assertDontSee('profile-chat-section-help', false)
            ->assertDontSee('profile-chat-appearance', false)
            ->assertDontSee('tab=appearance', false)
            ->assertSee('حالت فقط خواندنی')
            ->assertDontSee('profile-chat-save', false);

        // The old standalone tab URL redirects into the hub section.
        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/chat")
            ->assertRedirect('/consultant/settings/profile?tab=chat');
    }

    public function test_logout_is_no_longer_in_the_top_navigation(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        // The logout form lives in the hub's account section; the nav keeps
        // no action pointing at it, and its profile button is a direct link
        // into the hub (the dropdown is gone).
        $this->assertStringNotContainsString('action="http://'.$host.'/logout"', $html);
        $this->assertStringContainsString('topnav-profile', $html);
        $this->assertStringContainsString('/consultant/settings/profile', $html);
        $this->assertStringNotContainsString('data-topnav-dropdown', $html);
    }

    public function test_profile_name_and_email_update(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->patch("http://{$host}/consultant/settings/profile", [
            'name' => 'نام جدید',
            'email' => 'new-' . Str::lower(Str::random(6)) . '@example.test',
        ])->assertRedirect();

        $this->assertSame('نام جدید', $user->fresh()->name);
    }

    public function test_email_conflict_is_rejected_within_the_same_tenant(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);
        $colleague = User::factory()->consultant()->create([
            'tenant_id' => $tenant->id,
            'email' => 'taken-' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $this->actingAs($user)->patch("http://{$host}/consultant/settings/profile", [
            'name' => 'هرچیز',
            'email' => $colleague->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $editUrl = "http://{$host}/consultant/settings/profile?tab=edit";
        $this->actingAs($user)->from($editUrl)->put("http://{$host}/consultant/settings/profile/password", [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertRedirect($editUrl)->assertSessionHasErrors('current_password');

        $this->get($editUrl)->assertOk()
            ->assertSee('aria-label="تغییر رمز عبور"  data-profile-auto-open', false);

        $this->actingAs($user)->put("http://{$host}/consultant/settings/profile/password", [
            'current_password' => 'password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertRedirect();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('brand-new-secret', $user->fresh()->password)
        );
    }

    public function test_avatar_upload_and_delete(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)->put("http://{$host}/consultant/settings/profile/avatar", [
            // create() with an explicit mime avoids the GD dependency that
            // ->image() has, while still satisfying the image/mimes rules.
            'avatar' => UploadedFile::fake()->create('me.jpg', 5, 'image/jpeg'),
        ])->assertRedirect();

        $user = $user->fresh();
        $this->assertNotNull($user->avatar);
        $this->assertFileExists(public_path('tenants/'.$tenant->slug.'/'.$user->avatar));

        $this->actingAs($user)->delete("http://{$host}/consultant/settings/profile/avatar")
            ->assertRedirect();

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_disabled_feature_flag_hides_the_section_and_blocks_the_tab(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        site_override(['features' => ['settings_profile' => false]]);

        // The account home and its edit/password children all 404, exactly
        // like the old standalone routes did…
        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=profile")
            ->assertNotFound();
        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=edit")
            ->assertNotFound();

        // …while the hub itself falls through to a section that is enabled.
        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile")
            ->assertOk();
    }

    /**
     * The personal layer must change what *its owner* sees and nothing for
     * anyone else — the core promise of "just for me".
     */
    public function test_personal_theme_applies_only_to_its_owner(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $owner = $this->consultantFor($tenant);
        $other = $this->consultantFor($tenant);

        $owner->preferences = ['site' => ['theme' => ['colors' => ['primary' => '#FF00AA']]]];
        $owner->save();

        $ownerHtml = $this->actingAs($owner)->get("http://{$host}/consultant/dashboard")->getContent();
        $otherHtml = $this->actingAs($other)->get("http://{$host}/consultant/dashboard")->getContent();

        $this->assertStringContainsString('--c-primary: #FF00AA', $ownerHtml);
        $this->assertStringNotContainsString('--c-primary: #FF00AA', $otherHtml);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [, $host] = $this->tenantWithDomain();

        $this->get("http://{$host}/consultant/settings/profile")
            ->assertRedirect(route('login'));
    }
}
