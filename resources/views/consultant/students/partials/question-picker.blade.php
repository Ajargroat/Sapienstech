{{-- Fetched into the question-picker popup by consultant-exams.js. The card
     itself is the control: clicking anywhere toggles selection (the round
     badge shows the question's order position once picked). Tags only render
     for metadata the bank actually carries. --}}
@php
    $diffLabels = ['Easy' => 'آسان', 'Medium' => 'متوسط', 'Hard' => 'سخت'];
@endphp

<div class="picker-list">
    @forelse($bank as $q)
        <div class="picker-item" data-question-id="{{ $q->id }}"
             data-snippet="{{ \Illuminate\Support\Str::limit(trim((string) $q->question_text), 46) }}"
             role="checkbox" aria-checked="false" tabindex="0">
            <span class="picker-pos" aria-hidden="true"></span>
            <div class="picker-item-main">
                @if($q->question_image_path)
                    <div class="picker-figure">
                        <x-cropped-image
                            :path="$q->question_image_path"
                            :bbox="$q->question_image_bbox"
                            :max-width="340"
                        />
                    </div>
                @endif
                <p class="picker-q-text">{{ $q->question_text }}</p>
                @if($q->answers->isNotEmpty())
                    <ol class="picker-options">
                        @foreach($q->answers as $i => $opt)
                            <li>
                                <span class="opt-label">{{ persian_digits($i + 1) }}</span>
                                <span class="opt-text">{{ \Illuminate\Support\Str::limit(trim((string) $opt->answer_text), 90) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
                <span class="picker-meta">
                    @if($q->difficulty)
                        <span class="diff-chip diff--{{ strtolower($q->difficulty) }}">{{ $diffLabels[$q->difficulty] }}</span>
                    @endif
                    @if($q->corp)
                        <span class="tag-chip tag--corp"
                              @if(!empty($corpColors[$q->corp])) style="--corp-color: {{ $corpColors[$q->corp] }}" @endif
                        ><i class="far fa-building"></i> {{ $q->corp }}</span>
                    @endif
                    @if($q->subject)
                        <span class="tag-chip tag--lesson"><i class="fas fa-book-open"></i> {{ $q->subject }}</span>
                    @endif
                    @if($q->chapter_label)
                        <span class="tag-chip tag--chapter"><i class="fas fa-layer-group"></i> {{ persian_digits($q->chapter_label) }}</span>
                    @endif
                </span>
            </div>
        </div>
    @empty
        <p class="picker-empty">سوالی با این فیلترها در بانک پیدا نشد.</p>
    @endforelse
</div>

@if($bank->lastPage() > 1)
    <div class="picker-pager">
        <button type="button" class="picker-page" data-page="{{ $bank->currentPage() - 1 }}"
                @disabled($bank->onFirstPage()) aria-label="صفحه قبل">
            <i class="fas fa-chevron-right"></i>
        </button>
        <span>{{ persian_digits($bank->currentPage()) }} / {{ persian_digits($bank->lastPage()) }}</span>
        <button type="button" class="picker-page" data-page="{{ $bank->currentPage() + 1 }}"
                @disabled($bank->hasMorePages() === false) aria-label="صفحه بعد">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
@endif
