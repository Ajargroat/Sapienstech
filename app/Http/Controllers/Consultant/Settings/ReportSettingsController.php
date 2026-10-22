<?php

namespace App\Http\Controllers\Consultant\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ConfigWriter;
use App\Support\SettingsTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The report-card section of the profile hub (پروفایل → کارنامه), reached
 * through the profile route with `?tab=report`.
 *
 * It exists because the student report card has TWO numbers the consultant,
 * not the platform, owns:
 *
 *  - the pass line — the percentage a subject is judged against. It decides
 *    which subjects land in the student's «نقاط ضعف» list and the passed /
 *    not-passed colour of every rail. A konkour consultancy and a school do not
 *    agree on it, so it is a tenant value rather than a constant.
 *  - whether the participants' average may be shown at all. Off by default:
 *    competition is a choice the consultant makes, never a default.
 *
 * Both are written through ConfigWriter into the tenant's runtime layer at
 * `report.*`, so they take the same precedence and cache invalidation as every
 * other tenant knob (and never touch config/tenants/*.php files). Only the
 * tenant admin may publish; everyone else sees the panel read-only, which is
 * the exact behaviour of the chat section next door.
 *
 * Nothing here is Studio: these are product values a consultant edits, not a
 * theme lever.
 */
class ReportSettingsController extends Controller
{
    /** path => [validation rules, cast] */
    private const WRITABLE = [
        'report.pass_line' => [['integer', 'min:0', 'max:100'], 'int'],
        'report.report_show_class_average' => [['boolean'], 'bool'],
    ];

    public function index(Request $request): View
    {
        $values = [];

        foreach (array_keys(self::WRITABLE) as $path) {
            $values[$path] = site($path, null);
        }

        return view('consultant.settings.report', [
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'report',
            'isTenantAdmin' => $request->user() instanceof User && $request->user()->isTenantAdmin(),
            'values' => $values,
            // The student-side contract, so the preview cannot drift from the
            // number the report page actually resolves.
            'passLine' => \App\Support\ReportCard::passLine(),
            'showsClassAverage' => \App\Support\ReportCard::showsClassAverage(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isTenantAdmin(), 403);
        abort_unless(tenant() !== null, 404);

        $rules = [];
        foreach (self::WRITABLE as $path => $entry) {
            $rules[$this->fieldName($path)] = $entry[0];
        }

        // An unchecked switch arrives absent: normalize to an explicit 0 so
        // "off" is a publish, not a no-op. Blank numerics are dropped so the
        // layered baseline answers instead of a bogus 0.
        $input = $request->all();
        foreach (self::WRITABLE as $path => $entry) {
            $field = $this->fieldName($path);

            if ($entry[1] === 'bool') {
                $input[$field] = ((string) $request->input($field)) === '1' ? '1' : '0';
            } elseif ($entry[1] === 'int' && ($input[$field] ?? '') === '') {
                unset($input[$field]);
            }
        }
        $request->replace($input);

        $validated = $request->validate($rules);

        $changes = [];
        foreach (self::WRITABLE as $path => $entry) {
            $field = $this->fieldName($path);

            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $validated[$field];

            $changes[$path] = match ($entry[1]) {
                'bool' => $value === '1' || $value === true || $value === 'true',
                'int' => (int) $value,
                default => $value,
            };
        }

        if ($changes !== []) {
            ConfigWriter::publishForTenant(tenant(), $changes);
        }

        return redirect()
            ->route(SettingsTabs::hub('consultant'), ['tab' => 'report'])
            ->with('success', 'تنظیمات کارنامه ذخیره شد.');
    }

    /** `report.pass_line` → `report_pass_line`, the form field name. */
    private function fieldName(string $path): string
    {
        return str_replace('.', '_', $path);
    }
}
