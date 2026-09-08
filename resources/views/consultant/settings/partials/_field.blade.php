{{--
    Renders one studio field from its config/studio.php definition.

    Expects: $field (definition), $resolved (site tree), $overrides (dot-path
    set). Input names use bracket notation derived from the dotted path so
    Laravel validates the nested array by the same path the writer stores.
--}}
@php
    $path = $field['path'];
    $name = \App\Support\StudioSchema::htmlName($path);
    $value = \App\Support\StudioSchema::current($resolved, $path);
    $isOverridden = array_key_exists($path, $overrides);
    $control = $field['control'];

    // Controls that render several inputs (or none) can't be the target of a
    // single `for`; their group carries an aria-label instead.
    $idless = in_array($control, ['select', 'archetype', 'sections', 'list'], true);
@endphp

<div class="studio-field studio-field--{{ $control }} @if($isOverridden) is-overridden @endif"
     data-studio-field="{{ $path }}" data-live="{{ \App\Support\StudioSchema::liveMode($path) }}">
    <div class="studio-field-head">
        <span class="studio-field-titlerow">
            <label class="studio-field-label" @if(!$idless) for="studio-{{ md5($path) }}" @endif>
                {{ $field['label'] }}
                @if($isOverridden)<span class="studio-badge" title="این مورد تغییر کرده است">●</span>@endif
            </label>

            @if(!empty($field['hint']))
                {{-- Hover on desktop (CSS), tap on touch (theme-studio.js). --}}
                <button type="button" class="studio-hint" data-studio-hint aria-label="راهنمای این گزینه">
                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                    <span class="studio-hint-bubble" role="tooltip">{{ $field['hint'] }}</span>
                </button>
            @endif
        </span>

        @if($isOverridden && ($canPublishEveryone ?? false))
            <button type="button" class="studio-reset" data-reset-path="{{ $path }}" title="بازنشانی به پیش‌فرض">
                <i class="fas fa-undo" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    @switch($control)
        @case('color')
            <div class="studio-color">
                <input type="color" id="studio-{{ md5($path) }}" name="{{ $name }}"
                       value="{{ $value }}" data-color-pick>
                <input type="text" class="settings-input studio-color-text"
                       value="{{ $value }}" data-color-text readonly>
            </div>
            @break

        @case('select')
        @case('archetype')
            @php
                $options = $control === 'archetype'
                    ? array_combine(\App\Support\StudioSchema::archetypes(), \App\Support\StudioSchema::archetypes())
                    : ($field['options'] ?? []);

                // Radios instead of a native <select>: the popup is browser
                // chrome that can't be themed (white-on-white in dark mode).
                // These reuse the dashboard-filter chip styles verbatim.
                // A stored value matching no option used to silently submit
                // the first option; keep that contract — an unchecked radio
                // group would submit nothing at all.
                $matched = in_array((string) $value, array_map('strval', array_keys($options)), true);
            @endphp
            <div class="filter-chips studio-chips" role="radiogroup" aria-label="{{ $field['label'] }}">
                @foreach($options as $optValue => $optLabel)
                    <input type="radio" class="filter-chip-input"
                           id="studio-{{ md5($path) }}-{{ $loop->index }}"
                           name="{{ $name }}" value="{{ $optValue }}"
                           @checked($matched ? (string) $value === (string) $optValue : $loop->first)>
                    <label class="filter-chip" for="studio-{{ md5($path) }}-{{ $loop->index }}">{{ $optLabel }}</label>
                @endforeach
            </div>
            @break

        @case('toggle')
            <label class="studio-toggle">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" id="studio-{{ md5($path) }}" name="{{ $name }}" value="1" @checked((bool) $value)>
                <span>{{ $value ? 'روشن' : 'خاموش' }}</span>
            </label>
            @break

        @case('textarea')
            <textarea id="studio-{{ md5($path) }}" name="{{ $name }}" rows="4" class="settings-input">{{ $value }}</textarea>
            @break

        @case('number')
            <input type="number" id="studio-{{ md5($path) }}" name="{{ $name }}" value="{{ $value }}" class="settings-input">
            @break

        @case('range')
            @php
                $min  = $field['min'] ?? 0;
                $max  = $field['max'] ?? 100;
                $step = $field['step'] ?? 1;
                $unit = (string) ($field['unit'] ?? '');

                // The stored value's own unit wins: a tenant file naming
                // "2.5rem" must not be re-emitted as "2.5px" the first time
                // the bar moves. Unparseable values ("inherit", empty) leave
                // the bar neutral; the text input stays the source of truth.
                $num = null;
                if (is_string($value) && preg_match('/^(-?[0-9.]+)\s*([a-zA-Z%]*)$/', trim($value), $m)) {
                    $num  = $m[1];
                    $unit = $m[2] !== '' ? $m[2] : $unit;
                } elseif (is_numeric($value)) {
                    $num = (string) $value;
                }

                // HTML range values must be valid floats: a stored ".10" or
                // "-.02" (no leading digit) would be rejected by the browser
                // and silently snap the bar to its midpoint. Cast through
                // float so the bar opens at the real value.
                $barValue = $num === null ? ($min + $max) / 2 : (string) (float) $num;
            @endphp
            <div class="studio-range" data-studio-range
                 data-min="{{ $min }}" data-max="{{ $max }}" data-step="{{ $step }}" data-unit="{{ $unit }}">
                <input type="range" class="studio-range-bar" aria-label="{{ $field['label'] }}"
                       min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                       value="{{ $barValue }}">
                {{-- Deliberately type=text, never type=number: stored values
                     like ".94" or "inherit" are invalid number values, and a
                     browser would render them as empty and submit them as
                     "" — silently failing the field's own rules. --}}
                <input type="text" class="settings-input studio-range-text" id="studio-{{ md5($path) }}"
                       name="{{ $name }}" value="{{ $value }}" @if($unit === '') inputmode="decimal" @endif>
            </div>
            @break

        @case('image')
            <div class="studio-image">
                @if($value)
                    <img src="{{ tenant_asset($value) }}" alt="" class="studio-image-preview">
                @endif
                <input type="file" id="studio-{{ md5($path) }}" name="{{ $name }}" accept="image/*" class="settings-file">
                <span class="settings-hint">برای تغییر، فایلی انتخاب کنید؛ خالی بگذارید تا تغییر نکند.</span>
            </div>
            @break

        @case('sections')
            @php
                $current = is_array($value) ? $value : [];
                $locked = $field['locked'] ?? [];

                // Show every option, ordered by the saved list; locked rows
                // are re-pinned to their canonical slot so the form cannot
                // even offer a submission that moves or hides them.
                $ordered = \App\Support\StudioSchema::pinSections((static function () use ($field, $current) {
                    $ordered = array_values(array_intersect(array_keys($field['options']), $current));
                    foreach (array_keys($field['options']) as $sec) {
                        if (! in_array($sec, $ordered, true)) { $ordered[] = $sec; }
                    }
                    return $ordered;
                })(), $field);
            @endphp
            <div class="studio-sections" data-sections>
                @foreach($ordered as $sec)
                    @php $isLocked = in_array($sec, $locked, true); @endphp
                    <label class="studio-section @if($isLocked || in_array($sec, $current, true)) is-on @endif @if($isLocked) is-locked @endif" data-section-row>
                        <span class="studio-section-move">
                            @if($isLocked)
                                <i class="fas fa-lock" title="این بخش همیشه نمایش داده می‌شود و جابه‌جا نمی‌شود"></i>
                            @else
                                <button type="button" class="sec-move sec-move-up" aria-label="بالا"><i class="fas fa-chevron-up"></i></button>
                                <button type="button" class="sec-move sec-move-down" aria-label="پایین"><i class="fas fa-chevron-down"></i></button>
                            @endif
                        </span>
                        @if($isLocked)
                            {{-- Hidden, not a disabled checkbox: it must always
                                 submit, and it holds the row's pinned slot in
                                 DOM order (JS blocks crossing locked rows). --}}
                            <input type="hidden"
                                   name="{{ \App\Support\StudioSchema::htmlName($path, true) }}"
                                   value="{{ $sec }}">
                        @else
                            <input type="checkbox"
                                   name="{{ \App\Support\StudioSchema::htmlName($path, true) }}"
                                   value="{{ $sec }}"
                                   @checked(in_array($sec, $current, true))>
                        @endif
                        <span>{{ $field['options'][$sec] }}</span>
                    </label>
                @endforeach
            </div>
            @break

        @case('list')
            @php
                $rows = is_array($value) ? array_values($value) : [];
                $defs = $field['item'] ?? [];
                $max  = (int) ($field['max'] ?? 20);
            @endphp
            {{-- One repeater row per item; the <template> holds the pristine
                 row theme-studio.js clones for "add" (its __KEY__ placeholders
                 become a unique browser-side key, so abandoned rows never
                 collide with the stored indices). --}}
            <div class="studio-list" data-studio-list data-max="{{ $max }}">
                <div class="studio-list-rows" data-list-rows>
                    @foreach($rows as $i => $row)
                        @include('consultant.settings.partials._list-row', [
                            'defs' => $defs, 'name' => $name, 'rowKey' => (string) $i, 'row' => (array) $row, 'num' => $i + 1,
                        ])
                    @endforeach
                </div>
                <template data-list-template>
                    @include('consultant.settings.partials._list-row', [
                        'defs' => $defs, 'name' => $name, 'rowKey' => '__KEY__', 'row' => [], 'num' => 0,
                    ])
                </template>
                <button type="button" class="secondary-button studio-list-add" data-list-add @if(count($rows) >= $max) hidden @endif>
                    <i class="fas fa-plus" aria-hidden="true"></i> افزودن مورد
                </button>
                {{-- Row failures arrive as "path.<key>.<field>"; a JS-added row's
                     key is gone after a failed round-trip, so surface the first
                     row error here rather than on a row that no longer exists. --}}
                @if($errors->has($path.'.*'))
                    <span class="settings-error">{{ $errors->first($path.'.*') }}</span>
                @endif
            </div>
            @break

        @default
            <input type="text" id="studio-{{ md5($path) }}" name="{{ $name }}" value="{{ $value }}" class="settings-input">
    @endswitch

    @error($path)<span class="settings-error">{{ $message }}</span>@enderror
</div>
