<?php

namespace Tests\Feature\Consultant;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigWriter;
use App\Support\ReportCard;
use App\Support\SettingsTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The گزارش section of the profile hub (پروفایل → کارنامه): the pass line and
 * the class-average switch.
 *
 * The point of these tests is the wiring, not the markup: a saved value must
 * reach the STUDENT's report card through the same contract the page reads,
 * and a non-admin must not be able to publish it.
 */
class ReportSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget(Domain::cacheKey($host));

        return [$tenant, $host];
    }

    /** The tenant admin is the only role allowed to publish these values. */
    private function adminFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->tenantAdmin()->create(['tenant_id' => $tenant->id]);
    }

    public function test_report_section_appears_in_the_hub_for_a_tenant_admin(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->adminFor($tenant);

        $this->assertContains('report', array_column(SettingsTabs::visible('consultant'), 'key'));

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=report")
            ->assertOk()
            ->assertSee('profile-chat-section', false)
            ->assertSee('خط قبولی هر درس', false)
            ->assertSee('report_pass_line', false)
            ->assertSee('profile-chat-save', false);
    }

    public function test_admin_can_save_the_pass_line_and_the_student_page_reflects_it(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->adminFor($tenant);

        $this->assertSame(50.0, ReportCard::passLine());

        $this->actingAs($user)
            ->post("http://{$host}/consultant/settings/report", [
                'report_pass_line' => 70,
                'report_report_show_class_average' => '1',
            ])
            ->assertRedirect('/consultant/settings/profile?tab=report')
            ->assertSessionHasNoErrors();

        // The write must be visible through the same contract the student
        // page reads — not just stored somewhere.
        $this->assertSame(70.0, ReportCard::passLine());
        $this->assertTrue(ReportCard::showsClassAverage());
    }

    public function test_unchecking_the_class_average_switch_publishes_a_switch_off(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->adminFor($tenant);

        // Start from a real published "on", through the same writer the
        // controller uses. site_override() would NOT do: it layers above the
        // DB row, so a later DB write could never be seen through it.
        ConfigWriter::publishForTenant($tenant, ['report.report_show_class_average' => true]);
        $this->assertTrue(ReportCard::showsClassAverage());

        // The form posts the hidden 0 when the checkbox is unchecked.
        $this->actingAs($user)
            ->post("http://{$host}/consultant/settings/report", [
                'report_pass_line' => 50,
                'report_report_show_class_average' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(ReportCard::showsClassAverage(), 'off must be a write, not a silent no-op');
    }

    public function test_pass_line_outside_the_range_is_rejected(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->adminFor($tenant);

        $this->actingAs($user)
            ->post("http://{$host}/consultant/settings/report", ['report_pass_line' => 140])
            ->assertSessionHasErrors('report_pass_line');

        $this->assertSame(50.0, ReportCard::passLine(), 'a rejected value must not be stored');
    }

    public function test_non_admin_cannot_publish_report_settings(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        // A plain consultant (not the tenant admin) must be refused.
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertFalse($staff->isTenantAdmin());

        $this->actingAs($staff)
            ->post("http://{$host}/consultant/settings/report", ['report_pass_line' => 90])
            ->assertForbidden();

        $this->assertSame(50.0, ReportCard::passLine());
    }

    public function test_section_is_read_only_for_a_non_admin(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        app()->instance('tenant', $tenant);

        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)
            ->get("http://{$host}/consultant/settings/profile?tab=report")
            ->assertOk()
            ->assertSee('حالت فقط خواندنی', false)
            ->assertDontSee('profile-chat-save', false);
    }

    public function test_section_is_hidden_when_the_tenant_has_no_report_cards(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->adminFor($tenant);

        site_override(['features' => ['report_cards' => false]]);

        $this->assertNotContains('report', array_column(SettingsTabs::visible('consultant'), 'key'));

        $this->actingAs($user)
            ->get("http://{$host}/consultant/settings/profile?tab=report")
            ->assertNotFound();
    }
}
