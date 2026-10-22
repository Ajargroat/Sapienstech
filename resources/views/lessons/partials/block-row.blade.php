{{--
    One editable block row.

    Server contract (LessonEditorController::storeBlocks):
      blocks[INDEX][id]           existing row id, or empty for a new block
      blocks[INDEX][type]         one of LessonBlock::TYPES
      blocks[INDEX][source_page]  page the block came from
      blocks[INDEX][data][...]    that type's payload (LessonBlocks::normalise)

    `items` and `figure_ids` repeat with `[]` so the browser's own form
    serialisation yields the list shape the validator expects — no JS required
    to save, and JS only adds reordering.
--}}
@php
    $data = (array) ($block->data ?? []);
    $fieldError = fn (string $field) => $errors->first("blocks.{$index}.data.{$field}");
@endphp

<article class="lesson-block lesson-block--{{ $block->type }}" data-lesson-block data-type="{{ $block->type }}">
    <input type="hidden" name="blocks[{{ $index }}][id]" value="{{ $block->id }}">
    <input type="hidden" name="blocks[{{ $index }}][type]" value="{{ $block->type }}">
    <input type="hidden" name="blocks[{{ $index }}][data][__keep]" value="1">

    <header class="lesson-block-head">
        <span class="lesson-block-type">
            <i class="fas fa-grip-vertical" data-lesson-drag-handle title="برای جابه‌جایی بکشید"></i>
            {{ $blockTypeLabels[$block->type] ?? $block->type }}
        </span>

        <span class="lesson-block-tools">
            @if($block->is_dirty)
                <span class="lesson-chip lesson-chip--dirty" title="دست انسان روی این بلوک بوده">ویرایش‌شده</span>
            @endif
            @if($block->source_page)
                <button type="button" class="lesson-chip" data-lesson-page-jump="{{ $block->source_page }}" title="صفحهٔ کتاب را نشان بده">
                    صفحهٔ {{ persian_digits($block->source_page) }}
                </button>
            @endif
            <button type="button" class="lesson-icon-button" data-lesson-move="up" title="بالا"><i class="fas fa-arrow-up"></i></button>
            <button type="button" class="lesson-icon-button" data-lesson-move="down" title="پایین"><i class="fas fa-arrow-down"></i></button>
            <button type="button" class="lesson-icon-button lesson-icon-button--danger" data-lesson-remove title="حذف بلوک"><i class="fas fa-trash"></i></button>
        </span>
    </header>

    <div class="lesson-block-body">
        @switch($block->type)
            @case('heading')
                <label class="settings-field">
                    <span class="settings-field-label">متن تیتر</span>
                    <input type="text" class="settings-input" name="blocks[{{ $index }}][data][text]"
                           value="{{ $data['text'] ?? '' }}" required>
                    @if($fieldError('text'))<span class="lesson-field-error">{{ $fieldError('text') }}</span>@endif
                </label>
                <label class="settings-field settings-field--narrow">
                    <span class="settings-field-label">سطح</span>
                    <select class="settings-input" name="blocks[{{ $index }}][data][level]">
                        @foreach([2, 3] as $level)
                            <option value="{{ $level }}" @selected((int) ($data['level'] ?? 2) === $level)>H{{ $level }}</option>
                        @endforeach
                    </select>
                </label>
                @break

            @case('paragraph')
                <label class="settings-field">
                    <span class="settings-field-label">متن پاراگراف</span>
                    <textarea class="settings-input lesson-textarea" rows="5" name="blocks[{{ $index }}][data][text]" required>{{ $data['text'] ?? '' }}</textarea>
                    @if($fieldError('text'))<span class="lesson-field-error">{{ $fieldError('text') }}</span>@endif
                </label>
                @break

            @case('points')
                <div class="lesson-points" data-lesson-points>
                    @foreach((array) ($data['items'] ?? []) as $itemIndex => $item)
                        <div class="lesson-point-row" data-lesson-point>
                            <input type="text" class="settings-input lesson-point-icon" name="blocks[{{ $index }}][data][items][{{ $itemIndex }}][icon]"
                                   value="{{ $item['icon'] ?? '' }}" placeholder="☀️" maxlength="8" aria-label="نماد">
                            <input type="text" class="settings-input" name="blocks[{{ $index }}][data][items][{{ $itemIndex }}][lead]"
                                   value="{{ $item['lead'] ?? '' }}" placeholder="عنوان کوتاه (اختیاری)">
                            <input type="text" class="settings-input" name="blocks[{{ $index }}][data][items][{{ $itemIndex }}][text]"
                                   value="{{ $item['text'] ?? '' }}" placeholder="متن نکته" required>
                            <button type="button" class="lesson-icon-button lesson-icon-button--danger" data-lesson-remove-point title="حذف نکته"><i class="fas fa-xmark"></i></button>
                        </div>
                    @endforeach
                </div>
                @if($fieldError('items'))<span class="lesson-field-error">{{ $fieldError('items') }}</span>@endif
                <button type="button" class="secondary-button" data-lesson-add-point><i class="fas fa-plus"></i> افزودن نکته</button>
                @break

            @case('equation')
                <label class="settings-field">
                    <span class="settings-field-label">عبارت ریاضی (بدون تغییر از کتاب)</span>
                    <textarea class="settings-input lesson-textarea lesson-textarea--math" rows="2" dir="ltr" name="blocks[{{ $index }}][data][expr]" required>{{ $data['expr'] ?? '' }}</textarea>
                    @if($fieldError('expr'))<span class="lesson-field-error">{{ $fieldError('expr') }}</span>@endif
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">توضیح زیر معادله (اختیاری)</span>
                    <input type="text" class="settings-input" name="blocks[{{ $index }}][data][caption]" value="{{ $data['caption'] ?? '' }}">
                </label>
                @break

            @case('tip')
                <label class="settings-field">
                    <span class="settings-field-label">عنوان کادر (اختیاری)</span>
                    <input type="text" class="settings-input" name="blocks[{{ $index }}][data][title]"
                           value="{{ $data['title'] ?? '' }}" placeholder="آیا می‌دانستید؟">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">متن نکته</span>
                    <textarea class="settings-input lesson-textarea" rows="3" name="blocks[{{ $index }}][data][text]" required>{{ $data['text'] ?? '' }}</textarea>
                    @if($fieldError('text'))<span class="lesson-field-error">{{ $fieldError('text') }}</span>@endif
                </label>
                @break

            @case('figure')
                <input type="hidden" name="blocks[{{ $index }}][data][figure_id]" value="{{ $data['figure_id'] ?? '' }}" data-lesson-figure-input>
                <div class="lesson-figure-preview" data-lesson-figure-preview>
                    @if(! empty($data['figure_id']) && $block->figure)
                        <img src="{{ \App\Support\TenantUploads::url($block->figure->image_path) }}" alt="">
                    @else
                        <span class="lesson-empty-note">شکلی انتخاب نشده است. از ستون شکل‌ها یکی را بزنید.</span>
                    @endif
                </div>
                <label class="settings-field settings-field--narrow">
                    <span class="settings-field-label">جای تصویر</span>
                    <select class="settings-input" name="blocks[{{ $index }}][data][placement]">
                        @foreach(['wide' => 'تمام‌عرض', 'right' => 'کنار متن (راست)', 'left' => 'کنار متن (چپ)'] as $value => $label)
                            <option value="{{ $value }}" @selected(($data['placement'] ?? 'wide') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">زیرنویس (اختیاری)</span>
                    <input type="text" class="settings-input" name="blocks[{{ $index }}][data][caption]" value="{{ $data['caption'] ?? '' }}">
                </label>
                @break

            @case('figure_duo')
                @foreach([0, 1] as $slot)
                    <label class="settings-field">
                        <span class="settings-field-label">تصویر {{ persian_digits($slot + 1) }}</span>
                        <select class="settings-input" name="blocks[{{ $index }}][data][figure_ids][]">
                            <option value="">— انتخاب کنید —</option>
                            @foreach($figures as $figure)
                                <option value="{{ $figure->id }}" @selected((int) (($data['figure_ids'][$slot] ?? 0)) === (int) $figure->id)>
                                    شکل صفحهٔ {{ persian_digits($figure->page_number) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <label class="settings-field">
                    <span class="settings-field-label">زیرنویس (اختیاری)</span>
                    <input type="text" class="settings-input" name="blocks[{{ $index }}][data][caption]" value="{{ $data['caption'] ?? '' }}">
                </label>
                @if($fieldError('figure_ids'))<span class="lesson-field-error">{{ $fieldError('figure_ids') }}</span>@endif
                @break
        @endswitch

        <label class="settings-field settings-field--narrow lesson-source-field">
            <span class="settings-field-label">صفحهٔ منبع</span>
            <input type="number" min="1" class="settings-input" name="blocks[{{ $index }}][source_page]"
                   value="{{ $block->source_page }}">
        </label>
    </div>
</article>
