@php
    $actions = collect($actions ?? [])
        ->filter(fn ($action) => is_array($action) && !empty($action['href']) && !empty($action['label']))
        ->values();
@endphp

@if($actions->isNotEmpty())
    <div class="dash-quick">
        @foreach($actions as $action)
            <a
                href="{{ $action['href'] }}"
                class="dash-quick-item"
                @if(!empty($action['external']))
                    target="_blank" rel="noopener"
                @endif
            >
                <span class="dash-quick-icon dash-quick-icon--{{ $action['tone'] ?? 'primary' }}">
                    <i class="fas {{ $action['icon'] ?? 'fa-arrow-left' }}" aria-hidden="true"></i>
                </span>
                <span>{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
@endif
