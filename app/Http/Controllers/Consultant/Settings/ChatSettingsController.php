<?php

namespace App\Http\Controllers\Consultant\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ChatService;
use App\Support\ConfigWriter;
use App\Support\SettingsTabs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The chat settings tab (تنظیمات → گفتگو).
 *
 * Two halves:
 *  - tenant-wide chat behavior (groups, attachments, receipts, limits,
 *    student portal access) — written into the same runtime config layer the
 *    Appearance studio uses (ConfigWriter), so it flows through the exact
 *    same precedence + cache invalidation as any other tenant knob; only the
 *    tenant admin may publish these.
 *  - a pointer to the Appearance studio for colors/labels of the chat UI
 *    (chat inherits the tenant theme through CSS variables; there is nothing
 *    chat-specific to style).
 *
 * Only a curated allowlist of paths can be written here; every key is
 * validated with the same rules the studio would apply.
 */
class ChatSettingsController extends Controller
{
    /** path => [validation rules, cast] */
    private const WRITABLE = [
        'features.student_chat' => [['boolean'], 'bool'],
        'chat.groups.enabled' => [['boolean'], 'bool'],
        'chat.groups.max_members' => [['integer', 'min:2', 'max:500'], 'int'],
        'chat.attachments.enabled' => [['boolean'], 'bool'],
        'chat.attachments.max_kb' => [['integer', 'min:16', 'max:20480'], 'int'],
        'chat.read_receipts' => [['boolean'], 'bool'],
        'chat.typing_indicator' => [['boolean'], 'bool'],
        'chat.close_threads' => [['boolean'], 'bool'],
        'chat.message_max_length' => [['integer', 'min:100', 'max:20000'], 'int'],
        'chat.edit_window_minutes' => [['integer', 'min:1', 'max:10080'], 'int'],
        'chat.rate_limit_per_minute' => [['integer', 'min:1', 'max:120'], 'int'],
        'chat.idle_autoclose_days' => [['integer', 'min:0', 'max:365'], 'int'],
        'chat.greeting_text' => [['nullable', 'string', 'max:500'], 'raw'],
        'chat.placeholder_text' => [['nullable', 'string', 'max:200'], 'raw'],
    ];

    public function index(Request $request): View
    {
        return view('consultant.settings.chat', [
            'tabs' => SettingsTabs::visible('consultant'),
            'activeTab' => 'chat',
            'isTenantAdmin' => $request->user() instanceof User && $request->user()->isTenantAdmin(),
            'chatConfig' => $this->currentValues(),
            'groups' => [
                'consultant_flag' => (bool) site('features.direct_chat', false),
            ],
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

        // Booleans arrive from unchecked checkboxes as absent: normalize the
        // toggle fields to an explicit 0 before validation so "off" is a
        // write, not a no-op. Empty numeric/text fields are dropped: they
        // keep the layered baseline rather than writing a bogus value.
        $input = $request->all();
        foreach (self::WRITABLE as $path => $entry) {
            $field = $this->fieldName($path);

            if ($entry[1] === 'bool') {
                // Form toggles post a hidden "0" plus the checkbox "1".
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
                'raw' => is_string($value) && trim($value) !== '' ? trim($value) : null,
                default => $value,
            };
        }

        if ($changes !== []) {
            ConfigWriter::publishForTenant(tenant(), $changes);
        }

        return back()->with('success', 'تنظیمات گفتگو ذخیره شد.');
    }

    /** Current effective values, for rendering the form. */
    private function currentValues(): array
    {
        $values = [];

        foreach (self::WRITABLE as $path => $entry) {
            $value = site($path);

            $values[$path] = match ($entry[1]) {
                'bool' => (bool) $value,
                'int' => (int) $value,
                default => $value,
            };
        }

        return $values;
    }

    /** `chat.groups.enabled` → `chat_groups_enabled` (flat input names). */
    private function fieldName(string $path): string
    {
        return str_replace('.', '_', $path);
    }
}
