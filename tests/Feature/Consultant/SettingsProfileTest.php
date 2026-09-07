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
 * The settings hub: profile tab CRUD, the moved logout, feature gating, and
 * the per-user ("just for me") config layer applied by ApplyPersonalTheme.
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

    public function test_profile_tab_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile")
            ->assertOk()
            ->assertSee('تنظیمات')
            ->assertSee('پروفایل');
    }

    public function test_chat_tab_loads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/chat")
            ->assertOk()
            ->assertSee('گفتگو');
    }

    public function test_logout_is_no_longer_in_the_top_navigation(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        $html = $this->actingAs($user)
            ->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->getContent();

        // The logout form moved to the profile tab; the nav keeps no action
        // pointing at it.
        $this->assertStringNotContainsString('action="http://'.$host.'/logout"', $html);
        $this->assertStringContainsString('data-topnav-dropdown', $html);
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

        $this->actingAs($user)->put("http://{$host}/consultant/settings/profile/password", [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertSessionHasErrors('current_password');

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

    public function test_disabled_feature_flag_hides_the_tab_and_blocks_the_route(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->consultantFor($tenant);

        site_override(['features' => ['settings_profile' => false]]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile")
            ->assertNotFound();
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
