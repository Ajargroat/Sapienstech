{{-- Fetched into the create-exam modal by consultant-exams.js. --}}
@php
    $diffLabels = ['Easy' => 'آسان', 'Medium' => 'متوسط', 'Hard' => 'سخت'];
@endphp

<div class="picker-list">
    @forelse($bank as $q)
        <label class="picker-item" data-question-id="{{ $q->id }}">
            <input type="checkbox" class="picker-check" value="{{ $q->id }}" tabindex="-1">
            @if($q->question_image_path)
                <x-cropped-image
                    :path="$q->question_image_path"
                    :bbox="$q->question_image_bbox"
                    :max-width="200"
                    class="picker-thumb"
                />
            @else
                <span class="picker-thumb picker-thumb--empty"><i class="fas fa-question"></i></span>
            @endif
            <span class="picker-item-body">
                <span class="picker-q-text">{{ \Illuminate\Support\Str::limit($q->question_text, 150) }}</span>
                <span class="picker-meta">
                    @if($q->difficulty)
                        <span class="diff-chip diff--{{ strtolower($q->difficulty) }}">{{ $diffLabels[$q->difficulty] }}</span>
                    @endif
                    <span><i class="fas fa-list-ul"></i> {{ persian_digits($q->answers->count()) }} گزینه</span>
                    @if($q->question_number_in_book)
                        <span><i class="fas fa-hashtag"></i> {{ persian_digits($q->question_number_in_book) }}</span>
                    @endif
                </span>
            </span>
        </label>
    @empty
        <p class="picker-empty">سوالی با این مشخصات در بانک پیدا نشد.</p>
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
