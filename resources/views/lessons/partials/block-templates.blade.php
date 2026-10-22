{{--
    Blank block templates for the "افزودن بلوک" control.

    They live in one <template> per type so the JS clones real, already-correct
    markup instead of building field names by hand — two encoders for the same
    field contract is how an editor silently starts posting the wrong shape.

    `__INDEX__` is replaced by the client with the next free index.
--}}
<div class="lesson-block-templates" hidden>
    @foreach($blockTypeLabels as $type => $label)
        <template data-lesson-template="{{ $type }}">
            <article class="lesson-block lesson-block--{{ $type }}" data-lesson-block data-type="{{ $type }}">
                <input type="hidden" name="blocks[__INDEX__][id]" value="">
                <input type="hidden" name="blocks[__INDEX__][type]" value="{{ $type }}">
                <input type="hidden" name="blocks[__INDEX__][data][__keep]" value="1">

                <header class="lesson-block-head">
                    <span class="lesson-block-type">
                        <i class="fas fa-grip-vertical" data-lesson-drag-handle title="برای جابه‌جایی بکشید"></i>
                        {{ $label }}
                    </span>
                    <span class="lesson-block-tools">
                        <span class="lesson-chip lesson-chip--dirty">جدید</span>
                        <button type="button" class="lesson-icon-button" data-lesson-move="up" title="بالا"><i class="fas fa-arrow-up"></i></button>
                        <button type="button" class="lesson-icon-button" data-lesson-move="down" title="پایین"><i class="fas fa-arrow-down"></i></button>
                        <button type="button" class="lesson-icon-button lesson-icon-button--danger" data-lesson-remove title="حذف بلوک"><i class="fas fa-trash"></i></button>
                    </span>
                </header>

                <div class="lesson-block-body">
                    @switch($type)
                        @case('heading')
                            <label class="settings-field">
                                <span class="settings-field-label">متن تیتر</span>
                                <input type="text" class="settings-input" name="blocks[__INDEX__][data][text]" required>
                            </label>
                            <label class="settings-field settings-field--narrow">
                                <span class="settings-field-label">سطح</span>
                                <select class="settings-input" name="blocks[__INDEX__][data][level]">
                                    <option value="2">H2</option>
                                    <option value="3">H3</option>
                                </select>
                            </label>
                            @break

                        @case('paragraph')
                            <label class="settings-field">
                                <span class="settings-field-label">متن پاراگراف</span>
                                <textarea class="settings-input lesson-textarea" rows="5" name="blocks[__INDEX__][data][text]" required></textarea>
                            </label>
                            @break

                        @case('points')
                            <div class="lesson-points" data-lesson-points>
                                <div class="lesson-point-row" data-lesson-point>
                                    <input type="text" class="settings-input lesson-point-icon" name="blocks[__INDEX__][data][items][0][icon]" placeholder="☀️" maxlength="8" aria-label="نماد">
                                    <input type="text" class="settings-input" name="blocks[__INDEX__][data][items][0][lead]" placeholder="عنوان کوتاه (اختیاری)">
                                    <input type="text" class="settings-input" name="blocks[__INDEX__][data][items][0][text]" placeholder="متن نکته" required>
                                    <button type="button" class="lesson-icon-button lesson-icon-button--danger" data-lesson-remove-point title="حذف نکته"><i class="fas fa-xmark"></i></button>
                                </div>
                            </div>
                            <button type="button" class="secondary-button" data-lesson-add-point><i class="fas fa-plus"></i> افزودن نکته</button>
                            @break

                        @case('equation')
                            <label class="settings-field">
                                <span class="settings-field-label">عبارت ریاضی</span>
                                <textarea class="settings-input lesson-textarea lesson-textarea--math" rows="2" dir="ltr" name="blocks[__INDEX__][data][expr]" required></textarea>
                            </label>
                            <label class="settings-field">
                                <span class="settings-field-label">توضیح زیر معادله (اختیاری)</span>
                                <input type="text" class="settings-input" name="blocks[__INDEX__][data][caption]">
                            </label>
                            @break

                        @case('tip')
                            <label class="settings-field">
                                <span class="settings-field-label">عنوان کادر (اختیاری)</span>
                                <input type="text" class="settings-input" name="blocks[__INDEX__][data][title]" placeholder="آیا می‌دانستید؟">
                            </label>
                            <label class="settings-field">
                                <span class="settings-field-label">متن نکته</span>
                                <textarea class="settings-input lesson-textarea" rows="3" name="blocks[__INDEX__][data][text]" required></textarea>
                            </label>
                            @break

                        @case('figure')
                            <input type="hidden" name="blocks[__INDEX__][data][figure_id]" value="" data-lesson-figure-input>
                            <div class="lesson-figure-preview" data-lesson-figure-preview>
                                <span class="lesson-empty-note">شکلی انتخاب نشده است. از ستون شکل‌ها یکی را بزنید.</span>
                            </div>
                            <label class="settings-field settings-field--narrow">
                                <span class="settings-field-label">جای تصویر</span>
                                <select class="settings-input" name="blocks[__INDEX__][data][placement]">
                                    <option value="wide">تمام‌عرض</option>
                                    <option value="right">کنار متن (راست)</option>
                                    <option value="left">کنار متن (چپ)</option>
                                </select>
                            </label>
                            <label class="settings-field">
                                <span class="settings-field-label">زیرنویس (اختیاری)</span>
                                <input type="text" class="settings-input" name="blocks[__INDEX__][data][caption]">
                            </label>
                            @break

                        @case('figure_duo')
                            <p class="lesson-empty-note">این بلوک را از ستون شکل‌ها با انتخاب «دو تصویر کنار هم» بسازید.</p>
                            @break
                    @endswitch

                    <label class="settings-field settings-field--narrow lesson-source-field">
                        <span class="settings-field-label">صفحهٔ منبع</span>
                        <input type="number" min="1" class="settings-input" name="blocks[__INDEX__][source_page]" value="">
                    </label>
                </div>
            </article>
        </template>
    @endforeach
</div>
