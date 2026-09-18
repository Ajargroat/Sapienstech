{{--
    One row of a studio `list` control (see config/studio.php `item` defs).

    Expects: $defs (item definitions), $name (bracket-notation input prefix of
    the parent list), $rowKey (stored index, or the literal '__KEY__' inside
    the <template> clone source), $row (current values), $num (display index;
    0 renders blank — theme-studio.js renumbers after add/move/delete).

    Row inputs are named <list>[<rowKey>][<key>] so Laravel validates them
    against the schema's "path.*.key" wildcard rules. __KEY__ is replaced with
    a unique browser-side key on clone; StudioSchema::normalizeList() re-indexes
    whatever survives, so keys never reach the stored layer.

    Typed rows: a cell with data-show-for is only revealed when the row's
    discriminant radio group (data-list-type) selects one of its types;
    theme-studio.js does the hiding, and the server drops the values anyway.
--}}
@php
    $rowName = $name.'['.$rowKey.']';
@endphp
<div class="studio-list-row" data-list-row>
    <div class="studio-list-row-head">
        <span class="studio-list-num" data-list-num>{{ $num ?: '' }}</span>
        <span class="studio-list-move">
            <button type="button" class="sec-move list-move-up" aria-label="بالا"><i class="fas fa-chevron-up"></i></button>
            <button type="button" class="sec-move list-move-down" aria-label="پایین"><i class="fas fa-chevron-down"></i></button>
        </span>
        <button type="button" class="sec-move list-del" data-list-del aria-label="حذف این مورد" title="حذف">
            <i class="fas fa-trash" aria-hidden="true"></i>
        </button>
    </div>

    <div class="studio-list-fields">
        @foreach($defs as $def)
            @php
                $key  = $def['key'];
                $ctrl = $def['control'] ?? 'text';
                $val  = $row[$key] ?? null;
                $in   = $rowName.'['.$key.']';
                $id   = 'studio-'.md5($rowName.'-'.$key);
            @endphp
            @if($ctrl === 'hidden')
                <input type="hidden" name="{{ $in }}" value="{{ $val }}" data-block-id>
                @continue
            @endif
            <div class="studio-list-cell @if(!empty($def['discriminant'])) studio-list-cell--type @endif"
                 @if(!empty($def['show_for'])) data-show-for="{{ implode(' ', $def['show_for']) }}" @endif>
                <span class="studio-list-cell-label">{{ $def['label'] ?? $key }}</span>

                @switch($ctrl)
                    @case('color')
                        <div class="studio-color" data-block-color>
                            <input type="color" value="{{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $val) ? $val : '#000000' }}"
                                   data-block-color-picker aria-label="{{ $def['label'] ?? $key }}">
                            <input type="text" name="{{ $in }}" value="{{ $val }}" class="settings-input" data-block-color-value
                                   placeholder="پیش‌فرض" aria-label="{{ $def['label'] ?? $key }} (HEX)">
                        </div>
                        @break

                    @case('textarea')
                        <textarea name="{{ $in }}" rows="3" class="settings-input" aria-label="{{ $def['label'] ?? $key }}">{{ $val }}</textarea>
                        @break

                    @case('number')
                        <input type="number" name="{{ $in }}" value="{{ $val }}" class="settings-input" aria-label="{{ $def['label'] ?? $key }}"
                                                       @isset($def['min']) min="{{ $def['min'] }}" @endisset
                                                       @isset($def['max']) max="{{ $def['max'] }}" @endisset>
                        @break

                    @case('select')
                        @php
                            $options = $def['options'] ?? [];
                            // Same contract as the scalar select control: a
                            // value matching no option keeps the first one
                            // checked so the group can never submit nothing.
                            // Themed dropdown (radio rows inside), not a chip
                            // spread — see the studio _field partial.
                            $matched = in_array((string) $val, array_map('strval', array_keys($options)), true);
                            $keys = array_keys($options);
                            $displayKey = $matched ? $val : ($keys[0] ?? null);
                            $displayLabel = $displayKey === null ? '' : ($options[$displayKey] ?? (string) $displayKey);
                        @endphp
                        <div class="filter-select studio-select" data-filter-select
                             @if(!empty($def['discriminant'])) data-list-type @endif>
                            <button type="button" class="filter-select-trigger" data-filter-select-trigger
                                    aria-haspopup="true" aria-expanded="false" aria-label="{{ $def['label'] ?? $key }}">
                                <span class="filter-select-value" data-filter-select-display>{{ $displayLabel }}</span>
                                <i class="fas fa-chevron-down filter-select-caret" aria-hidden="true"></i>
                            </button>
                            <div class="filter-select-list" data-filter-select-list aria-label="{{ $def['label'] ?? $key }}">
                                @foreach($options as $optValue => $optLabel)
                                    <label class="filter-select-option" data-filter-select-option data-label="{{ $optLabel }}">
                                        <input type="radio" name="{{ $in }}" value="{{ $optValue }}"
                                               @checked($matched ? (string) $val === (string) $optValue : $loop->first)>
                                        <span>{{ $optLabel }}</span>
                                        <i class="fas fa-check filter-select-option-mark" aria-hidden="true"></i>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case('toggle')
                        {{-- Hidden "0" first: an unchecked row toggle still
                             submits, and normalizeList reads it as false. --}}
                        <label class="studio-toggle">
                            <input type="hidden" name="{{ $in }}" value="0">
                            <input type="checkbox" name="{{ $in }}" value="1" @checked((bool) $val)>
                            <span>{{ $val ? 'روشن' : 'خاموش' }}</span>
                        </label>
                        @break

                    @default
                        <input type="text" name="{{ $in }}" value="{{ $val }}" class="settings-input" aria-label="{{ $def['label'] ?? $key }}">
                @endswitch
            </div>
        @endforeach
    </div>
</div>
