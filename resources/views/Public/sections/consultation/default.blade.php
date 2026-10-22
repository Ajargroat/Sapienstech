{{--
    resources/views/public/sections/consultation/default.blade.php

    The visitor's side of a consultation request («درخواست مشاوره»): they leave
    a name, an email, an optional phone and a message, and the row waits in
    `consultation_requests` for a tenant admin/owner to triage it. Nothing here
    becomes a roster row — the submission is one-way, and the staff email rides
    the queue (App\Mail\ConsultationRequestReceived).

    The tenant comes from the host (IdentifyTenant), never from this form: the
    POST route carries no tenant field, so a crafted submission cannot aim at
    someone else's site (the /join idiom).

    Styling mirrors sections/join/default.blade.php — the one other public
    form — and uses only theme tokens, so this section inherits every
    archetype's look. The request schema is fixed (name + email + optional
    phone + message are exactly what StoreConsultationRequest validates), so
    the field labels live here like join's do, not in tenant config; the
    tenant owns heading, subheading and the submit label.
--}}
@php
    $c      = $cfg;
    $reveal = $anim['reveal'] ?? true;

    // Structural field labels: the public form is a fixed, enumerable surface.
    // A config value can never emit an arbitrary field name.
    $fields = [
        'name'    => ['label' => 'نام و نام خانوادگی',       'type' => 'text',  'required' => true,  'autocomplete' => 'name'],
        'email'   => ['label' => 'ایمیل',                    'type' => 'email', 'required' => true,  'autocomplete' => 'email'],
        'phone'   => ['label' => 'شمارهٔ تماس (اختیاری)',     'type' => 'tel',   'required' => false, 'autocomplete' => 'tel'],
        'message' => ['label' => 'پیام شما',                 'type' => 'textarea', 'required' => true, 'autocomplete' => 'off'],
    ];

    $inputStyle = 'background:var(--c-surface-alt);border:1px solid var(--c-border);'
                .'border-radius:var(--radius-input);color:var(--c-text)';
@endphp

<section id="{{ $c['id'] ?? 'consultation' }}" class="lp-section {{ $reveal ? 'reveal' : '' }}">
    <div class="landing-container" style="max-width:var(--measure);margin-inline:auto">
        @include('public.sections._heading', [
            'heading'    => $c['heading'] ?? null,
            'subheading' => $c['subheading'] ?? null,
            'path'       => 'public.landing.consultation',
        ])

        <div class="p-8 rounded-[var(--radius-card)]"
             style="background:color-mix(in srgb, var(--c-surface) calc({{ $theme['effects']['glass_opacity'] }} * 100%), transparent);
                    border:1px solid var(--c-border);
                    box-shadow:var(--card-shadow)">
            {{-- A stored submission or a validation failure both land back here. --}}
            @if (session('consultation_status'))
                <div class="text-sm rounded-[var(--radius-input)] p-3 mb-6"
                     role="status"
                     style="background:color-mix(in srgb, var(--c-success) 10%, transparent);border:1px solid var(--c-success);color:var(--c-success)">
                    {{ session('consultation_status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="text-sm rounded-[var(--radius-input)] p-3 mb-6"
                     role="alert"
                     style="background:color-mix(in srgb, var(--c-danger) 10%, transparent);border:1px solid var(--c-danger);color:var(--c-danger)">
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('consultation.store') }}">
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($fields as $key => $meta)
                        <div class="{{ $key === 'message' ? 'md:col-span-2' : '' }}">
                            <label for="consultation-{{ $key }}" class="block mb-1 text-sm font-medium">
                                {{ $meta['label'] }}
                                @if ($meta['required']) <span aria-hidden="true">*</span> @endif
                            </label>
                            @if ($meta['type'] === 'textarea')
                                <textarea id="consultation-{{ $key }}"
                                          name="{{ $key }}"
                                          rows="4"
                                          @if ($meta['required']) required @endif
                                          class="w-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                                          style="{{ $inputStyle }}">{{ old($key) }}</textarea>
                            @else
                                <input
                                    id="consultation-{{ $key }}"
                                    type="{{ $meta['type'] }}"
                                    name="{{ $key }}"
                                    value="{{ old($key) }}"
                                    autocomplete="{{ $meta['autocomplete'] }}"
                                    @if ($meta['required']) required @endif
                                    @if (in_array($key, ['email', 'phone'], true)) dir="ltr" @endif
                                    class="w-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                                    style="{{ $inputStyle }}"
                                >
                            @endif
                        </div>
                    @endforeach
                </div>

                <button type="submit"
                        class="w-full mt-6 font-semibold rounded-[var(--radius-button)] py-2.5 transition-colors hover:opacity-90"
                        style="background:var(--c-primary);color:var(--c-on-primary)">
                    {{ $c['submit_label'] ?? 'ارسال درخواست' }}
                </button>
            </form>
        </div>
    </div>
</section>
