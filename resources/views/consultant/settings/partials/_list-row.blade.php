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
            <div class="studio-list-cell @if(!empty($def['discriminant'])) studio-list-cell--type @endif"
                 @if(!empty($def['show_for'])) data-show-for="{{ implode(' ', $def['show_for']) }}" @endif>
                <span class="studio-list-cell-label">{{ $def['label'] ?? $key }}</span>

                @switch($ctrl)
                    @case('textarea')
                        <textarea name="{{ $in }}" rows="3" class="settings-input" aria-label="{{ $def['label'] ?? $key }}">{{ $val }}</textarea>
                        @break

                    @case('number')
                        <input type="number" name="{{ $in }}" value="{{ $val }}" class="settings-input" aria-label="{{ $def['label'] ?? $key }}">
                        @break

                    @case('select')
                        @php
                            $options = $def['options'] ?? [];
                            // Same contract as the scalar select control: a
                            // value matching no option keeps the first one
                            // checked so the group can never submit nothing.
                            $matched = in_array((string) $val, array_map('strval', array_keys($options)), true);
                        @endphp
                        <div class="filter-chips studio-chips" role="radiogroup" aria-label="{{ $def['label'] ?? $key }}"
                             @if(!empty($def['discriminant'])) data-list-type @endif>
                            @foreach($options as $optValue => $optLabel)
                                <input type="radio" class="filter-chip-input"
                                       id="{{ $id }}-{{ $loop->index }}"
                                       name="{{ $in }}" value="{{ $optValue }}"
                                       @checked($matched ? (string) $val === (string) $optValue : $loop->first)>
                                <label class="filter-chip" for="{{ $id }}-{{ $loop->index }}">{{ $optLabel }}</label>
                            @endforeach
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
