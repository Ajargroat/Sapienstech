@extends('consultant.settings.layout')

@section('settings-content')
<div class="settings-cards">
    <section class="settings-card">
        <h3 class="settings-card-title">{{ $labels['settings_chat'] ?? 'گفتگو' }}</h3>

        <div class="feature-placeholder">
            <span class="feature-placeholder-icon">
                <i class="fas fa-comments"></i>
            </span>
            <h2>به‌زودی</h2>
            <p>امکان گفتگوی مستقیم با دانش‌آموزان در حال توسعه است. تنظیمات این بخش پس از راه‌اندازی گفتگو فعال می‌شود.</p>
        </div>
    </section>
</div>
@endsection
