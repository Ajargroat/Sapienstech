@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>{{ $labels['profile_heading'] }}</h1>
            <p>
                <a href="{{ route('consultant.dashboard') }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> {{ $labels['back_to_students'] }}
                </a>
            </p>
        </div>
    </div>

    @if(session('status'))
        <div class="profile-flash">{{ session('status') }}</div>
    @endif

    <section class="panel student-profile-panel">
        <div class="student-profile-head">
            <span class="student-avatar-lg">
                @if($student->avatar)
                    <img src="{{ asset('storage/' . $student->avatar) }}" alt="{{ $student->name }}" class="student-avatar-img">
                @else
                    {{ mb_substr($student->name, 0, 1) }}
                @endif
            </span>
            <div>
                <h2>{{ $student->name }}</h2>
                <span class="student-email">{{ $student->email }}</span>
            </div>
        </div>

        <div class="student-profile-grid">
            @foreach(($profile['display_fields'] ?? ['grade' => 'student_grade', 'gender' => 'student_gender', 'major' => 'student_major']) as $column => $labelKey)
                <div class="profile-field">
                    <span class="profile-field-label">{{ $labels[$labelKey] ?? $labelKey }}</span>
                    <span class="profile-field-value">{{ $student->{$column} ?: '—' }}</span>
                </div>
            @endforeach
        </div>

        <div class="student-profile-links">
            <a href="{{ route('consultant.student.report-card', $student) }}" class="secondary-button">
                <i class="fas fa-chart-line"></i> کارنامه
            </a>
            <a href="{{ route('consultant.student.exams', $student) }}" class="secondary-button">
                <i class="fas fa-tasks"></i> آزمون‌ها
            </a>
            <a href="{{ route('consultant.student.schedule', $student) }}" class="secondary-button">
                <i class="fas fa-calendar-alt"></i> برنامه
            </a>
            <a href="{{ route('consultant.student.source-permissions', $student) }}" class="secondary-button">
                <i class="fas fa-shield-alt"></i> دسترسی منابع
            </a>
        </div>

        @if(!empty($profile['form']['fields']))
            <form
                method="POST"
                action="{{ route('consultant.student.profile.update', $student) }}"
                enctype="multipart/form-data"
                class="profile-form-section"
            >
                @csrf
                @method($profile['form']['method'] ?? 'PUT')

                @if(!empty($profile['avatar']))
                    <div class="profile-avatar-section">
                        <input
                            type="file"
                            name="avatar"
                            id="avatar-input"
                            accept="{{ implode(',', array_map(fn ($m) => '.' . trim($m), explode(',', $profile['avatar']['mimes'] ?? 'jpeg,jpg,png,webp'))) }}"
                            class="hidden"
                        >
                        <label for="avatar-input" class="avatar-upload-btn">
                            <i class="fas fa-camera"></i> {{ $labels['upload_photo'] }}
                        </label>
                        <span class="avatar-upload-hint" id="avatar-filename">
                            JPG, PNG, WebP — حداکثر {{ persian_digits($profile['avatar']['max_size_kb'] ?? 1024) }} کیلوبایت
                        </span>
                    </div>
                @endif

                <h3>{{ $labels['profile_form_heading'] }}</h3>

                <div class="profile-form-grid">
                    @foreach($profile['form']['fields'] as $field)
                        <div class="profile-form-field {{ ($field['key'] ?? '') === 'name' ? 'full-width' : '' }}">
                            <label for="profile-{{ $field['key'] }}">
                                {{ $labels[$field['label']] ?? $field['label'] }}
                                @if($field['required'] ?? false)
                                    <span class="profile-form-required">*</span>
                                @endif
                            </label>
                            <input
                                type="{{ $field['type'] ?? 'text' }}"
                                id="profile-{{ $field['key'] }}"
                                name="{{ $field['key'] }}"
                                value="{{ $student->{$field['key']} ?? '' }}"
                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                @if($field['required'] ?? false) required @endif
                                @if($field['ltr'] ?? false) dir="ltr" @endif
                            >
                            @error($field['key'])
                                <span class="profile-form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    @endforeach
                </div>

                @error('avatar')
                    <span class="profile-form-error">{{ $message }}</span>
                @enderror

                <div class="profile-form-actions">
                    <button type="submit" class="primary-button">{{ $labels['save_changes'] }}</button>
                </div>
            </form>
        @endif
    </section>

    <script>
        const avatarInput = document.getElementById('avatar-input');
        if (avatarInput) {
            avatarInput.addEventListener('change', function () {
                const hint = document.getElementById('avatar-filename');
                if (hint && this.files.length) {
                    hint.textContent = this.files[0].name;
                }
            });
        }
    </script>
@endsection
