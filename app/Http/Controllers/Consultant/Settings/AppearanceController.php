<?php

namespace App\Http\Controllers\Consultant\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ApplyPersonalTheme;
use App\Http\Requests\Consultant\Settings\StudioSaveRequest;
use App\Models\User;
use App\Models\WebsiteConfig;
use App\Support\ConfigWriter;
use App\Support\SettingsTabs;
use App\Support\SiteConfig;
use App\Support\StudioSchema;
use App\Support\TenantUploads;
use App\Support\ThemeTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * The Appearance studio: a schema-driven editor over the tenant's runtime
 * config layer (website_configs.layout_config) and the per-user personal
 * layer (preferences.site).
 *
 * Three scopes, matching the product decision:
 *   - «برای همه»  writes the tenant layer (public site + every visitor).
 *                Gated to tenant_admin unless the admin enabled
 *                features.appearance_staff_publish — the admin decides.
 *   - «فقط برای من» writes the user's personal layer, applied per request
 *                by ApplyPersonalTheme.
 *   - «پیش‌نمایش» writes neither: the diff goes to the session and is layered
 *                on top for the previewer only, until they exit preview.
 *
 * What is editable is exactly config/studio.php — paths outside it are
 * invisible to the form and rejected by the request.
 */
class AppearanceController extends Controller
{
    public function index(Request $request): View
    {
        $resolved = site();

        $row = WebsiteConfig::withoutGlobalScopes()
            ->where('tenant_id', tenant()->id)
            ->first();

        return view('consultant.settings.appearance', [
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'appearance',
            'resolved' => $resolved,
            'overrides' => StudioSchema::overriddenPaths($row?->layout_config),
            'canPublishEveryone' => $this->canPublishEveryone($request->user()),
            'isTenantAdmin' => $this->isTenantAdmin($request),
            'previewing' => session()->has('studio.preview'),
        ]);
    }

    public function save(StudioSaveRequest $request): RedirectResponse
    {
        // Diff against the saved state with the session preview stripped:
        // what a save persists is exactly what the form shows, including any
        // values that currently only live in the preview layer.
        $site = app(SiteConfig::class);
        $site->personalize(ApplyPersonalTheme::savedLayer($request));
        $resolved = $site->all();
        $values = $request->studioValues();

        // Image controls: an uploaded file replaces the stored path; no file
        // means "unchanged" (the scalar pipeline never sees it).
        foreach (array_keys(StudioSchema::imageRules()) as $path) {
            $field = StudioSchema::field($path);

            if (($field['group_admin'] ?? false) && ! $this->isTenantAdmin($request)) {
                continue;
            }

            if ($request->hasFile($path)) {
                $values[$path] = TenantUploads::store(
                    $request->file($path),
                    $field['folder'] ?? 'brand',
                    Arr::get($resolved, $path),
                );
            }
        }

        $diff = StudioSchema::diff($values, $resolved);

        return match ($request->input('scope')) {
            'everyone' => $this->saveForEveryone($request, $diff),
            'preview' => $this->savePreview($diff),
            default => $this->saveForMe($request, $diff),
        };
    }

    /**
     * Forget one whitelisted key so the lower layers show through again.
     */
    public function resetKey(Request $request): RedirectResponse
    {
        $path = (string) $request->input('path', '');
        $scope = (string) $request->input('scope', 'everyone');

        abort_unless(StudioSchema::field($path) !== null, 404);

        if ($scope === 'me') {
            ConfigWriter::saveForUser($request->user(), [$path => null]);

            return back()->with('success', 'این مورد برای نمای شما بازنشانی شد.');
        }

        abort_unless($this->canPublishEveryone($request->user()), 403);
        ConfigWriter::publishForTenant(tenant(), [$path => null]);

        return back()->with('success', 'این مورد برای همه بازدیدکنندگان بازنشانی شد.');
    }

    /**
     * Drop the whole tenant runtime layer (everything falls back to the
     * files). The personal layer is reset from the profile tab instead.
     */
    public function resetAll(Request $request): RedirectResponse
    {
        abort_unless($this->canPublishEveryone($request->user()), 403);

        ConfigWriter::publishForTenant(tenant(), array_fill_keys(
            array_keys(StudioSchema::overriddenPaths(
                WebsiteConfig::withoutGlobalScopes()->where('tenant_id', tenant()->id)->first()?->layout_config
            )),
            null,
        ));

        return back()->with('success', 'همهٔ تنظیمات ظاهری به حالت پیش‌فرض برگشت.');
    }

    public function exitPreview(Request $request): RedirectResponse
    {
        $request->session()->forget('studio.preview');

        return back()->with('success', 'حالت پیش‌نمایش بسته شد.');
    }

    /**
     * The live-preview sink: validates the whole form against the same
     * whitelist as a save, parks the diff in the session preview layer (so a
     * manual preview reload shows it too), and returns the freshly derived
     * token map so the studio can repaint the preview without a page reload.
     *
     * Nothing is persisted here — this is the «پیش‌نمایش» scope on a timer.
     */
    public function live(StudioSaveRequest $request): JsonResponse
    {
        $site = app(SiteConfig::class);

        // Baseline without the session preview: the form's diff is restated
        // in full on every sync, so an untouched form keeps the preview it
        // was rendered with instead of clearing it.
        $site->personalize(ApplyPersonalTheme::savedLayer($request));
        $diff = StudioSchema::diff($request->studioValues(), $site->all());

        if ($diff === []) {
            $request->session()->forget('studio.preview');
        } else {
            $request->session()->put('studio.preview', self::nestedLayer($diff));
        }

        // Re-resolve with the fresh layer so the returned tokens match
        // exactly what the next render of the preview will emit.
        $site->personalize(ApplyPersonalTheme::effectiveLayer($request));
        $theme = $site->get('theme');

        return response()->json([
            'vars'    => $theme['vars'] ?? [],
            'schemes' => ThemeTokens::schemeVars($theme),
            'changed' => array_keys($diff),
        ]);
    }

    protected function saveForEveryone(StudioSaveRequest $request, array $diff): RedirectResponse
    {
        abort_unless($this->canPublishEveryone($request->user()), 403);

        ConfigWriter::publishForTenant(tenant(), $diff);
        $request->session()->forget('studio.preview');

        return redirect()
            ->route('consultant.settings.appearance')
            ->with('success', $diff === []
                ? 'تغییری برای اعمال وجود نداشت.'
                : 'تغییرات برای همهٔ بازدیدکنندگان اعمال شد.');
    }

    protected function saveForMe(StudioSaveRequest $request, array $diff): RedirectResponse
    {
        ConfigWriter::saveForUser($request->user(), $diff);
        $request->session()->forget('studio.preview');

        return redirect()
            ->route('consultant.settings.appearance')
            ->with('success', $diff === []
                ? 'تغییری برای اعمال وجود نداشت.'
                : 'تغییرات فقط برای نمای شما ذخیره شد.');
    }

    protected function savePreview(array $diff): RedirectResponse
    {
        session(['studio.preview' => self::nestedLayer($diff)]);

        return redirect()
            ->route('consultant.settings.appearance')
            ->with('success', 'حالت پیش‌نمایش فعال شد؛ با «خروج از پیش‌نمایش» به حالت ذخیره‌شده برمی‌گردید.');
    }

    /**
     * Build a nested layer from a dotted diff; the session copy is what
     * ApplyPersonalTheme layers on top for this user only.
     */
    protected static function nestedLayer(array $diff): array
    {
        $layer = [];

        foreach ($diff as $path => $value) {
            Arr::set($layer, $path, $value);
        }

        return $layer;
    }

    /**
     * Decision #1: the tenant admin is in charge. Site-wide publication is
     * admin-only unless the admin explicitly enabled staff publishing.
     */
    protected function canPublishEveryone(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->isTenantAdmin()
            || (bool) site('features.appearance_staff_publish', false);
    }

    protected function isTenantAdmin(Request $request): bool
    {
        return $request->user() instanceof User && $request->user()->isTenantAdmin();
    }
}
