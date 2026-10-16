{{--
    resources/views/public/sections/join/default.blade.php

    The visitor's side of a join request («درخواست عضویت»): they import their
    own details and declare the payment they made, and the record waits for a
    tenant admin/owner to approve it. Nothing here touches the roster — the
    submission lands in `student_join_requests`, so an unapproved visitor never
    appears in a student list, a count or a chat picker.

    The tenant comes from the host (IdentifyTenant), never from this form: the
    POST route carries no tenant field, so a crafted submission cannot file a
    request against someone else's site.

    Styling mirrors auth/login.blade.php — the one other public form — and uses
    only theme tokens, so this section inherits every archetype's look.
--}}
@php
    $j      = $cfg;
    $reveal = $anim['reveal'] ?? true;

    // Which inputs to render. Whitelisted so a config value can never emit an
    // arbitrary field name (the request's `fields` is tenant-authored config,
    // but the form is public — keep the surface enumerable).
    $allowed = [
        'name'      => ['label' => 'نام و نام خانوادگی', 'type' => 'text',  'required' => true,  'autocomplete' => 'name'],
        'phone'     => ['label' => 'شمارهٔ موبایل',      'type' => 'tel',   'required' => true,  'autocomplete' => 'tel'],
        'email'     => ['label' => 'ایمیل (اختیاری)',    'type' => 'email', 'required' => false, 'autocomplete' => 'email'],
        'national_code' => ['label' => 'کد ملی (اختیاری)', 'type' => 'text', 'required' => false, 'autocomplete' => 'off'],
        'grade'     => ['label' => 'پایه تحصیلی',        'type' => 'text',  'required' => false, 'autocomplete' => 'off'],
        'gender'    => ['label' => 'جنسیت',              'type' => 'text',  'required' => false, 'autocomplete' => 'off'],
        'major'     => ['label' => 'رشته تحصیلی',        'type' => 'text',  'required' => false, 'autocomplete' => 'off'],
        'bio'       => ['label' => 'توضیح کوتاه',        'type' => 'text',  'required' => false, 'autocomplete' => 'off'],
        'reference' => ['label' => 'کد پیگیری پرداخت',   'type' => 'text',  'required' => false, 'autocomplete' => 'off'],
    ];

    // Which optional inputs to render. `fields` is a studio list of rows, so it
    // arrives as [['field' => 'phone'], ...]; the values are whitelisted here so
    // a config value can never emit an arbitrary input name (the form is public).
    $configured = array_map(
        fn ($row) => is_array($row) ? ($row['field'] ?? '') : (string) $row,
        (array) ($j['fields'] ?? [])
    );

    // `name` and `phone` are structural: a request without them cannot become a
    // student. Everything else is the tenant's choice.
    $wanted = array_values(array_intersect(
        array_merge(['name', 'phone'], $configured),
        array_keys($allowed)
    ));

    $inputStyle = 'background:var(--c-surface-alt);border:1px solid var(--c-border);'
                .'border-radius:var(--radius-input);color:var(--c-text)';

    // The tenant's priced plans. When present, the visitor picks a plan by its
    // slug and the amount is resolved from it server-side (App\Support\Plans);
    // when absent, this form is exactly what it was before plans existed.
    $plans     = \App\Support\Plans::options();
    $oldPlan   = old('plan_slug', array_key_first($plans) ?? '');
@endphp

<section id="{{ $j['id'] ?? 'join' }}" class="lp-section {{ $reveal ? 'reveal' : '' }}">
    <div class="landing-container" style="max-width:var(--measure);margin-inline:auto">
        @include('public.sections._heading', [
            'heading'    => $j['heading'] ?? null,
            'subheading' => $j['subheading'] ?? null,
            'path'       => 'public.landing.join',
        ])

        <div class="p-8 rounded-[var(--radius-card)]"
             style="background:color-mix(in srgb, var(--c-surface) calc({{ $theme['effects']['glass_opacity'] }} * 100%), transparent);
                    border:1px solid var(--c-border);
                    box-shadow:var(--card-shadow)">
            {{-- A stored submission or a validation failure both land back here. --}}
            @if (session('join_status'))
                <div class="text-sm rounded-[var(--radius-input)] p-3 mb-6"
                     role="status"
                     style="background:color-mix(in srgb, var(--c-success) 10%, transparent);border:1px solid var(--c-success);color:var(--c-success)">
                    {{ session('join_status') }}
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

            @if (!empty($j['payment_note']) || (!empty($j['amount']) && $plans === []) || !empty($j['pay_button']['href']))
                <div class="text-sm rounded-[var(--radius-input)] p-4 mb-6"
                     style="background:var(--c-surface-alt);border:1px solid var(--c-border);color:var(--c-muted)">
                    @if (!empty($j['payment_note']))
                        <p>{{ $j['payment_note'] }}</p>
                    @endif
                    {{-- The section's own `amount` is a single fixed hint. With
                         plans configured each plan states its own price, so
                         this would contradict the chooser below. --}}
                    @if (!empty($j['amount']) && $plans === [])
                        <p class="mt-2" style="color:var(--c-text)">
                            مبلغ: <b>{{ persian_digits(number_format((int) $j['amount'])) }}</b> تومان
                        </p>
                    @endif
                    @if (!empty($j['pay_button']['href']) && ($j['pay_button']['visible'] ?? true))
                        @include('public.sections._button', [
                            'label' => $j['pay_button']['label'] ?? 'پرداخت',
                            'href'  => $j['pay_button']['href'],
                            'tone'  => $j['pay_button']['style'] ?? 'primary',
                            'icon'  => $j['pay_button']['icon'] ?? null,
                            'block' => false,
                            'path'  => 'public.landing.join.pay_button',
                        ])
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('join.store') }}">
                @csrf

                {{-- Plan chooser: the visitor names a plan, never a price. The
                     price shown is display-only; the amount stored on the
                     request is read from config by slug on the server. --}}
                @if ($plans !== [])
                    <fieldset class="mb-6">
                        <legend class="block mb-2 text-sm font-medium">طرح انتخابی</legend>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach ($plans as $slug => $plan)
                                <label class="flex items-start gap-3 p-3 cursor-pointer"
                                       style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input)">
                                    <input type="radio" name="plan_slug" value="{{ $slug }}"
                                           @checked($oldPlan === $slug)
                                           class="mt-1 focus:ring-2 focus:ring-(--c-primary)">
                                    <span>
                                        <b class="block">{{ $plan['name'] ?? $slug }}</b>
                                        <span class="text-sm" style="color:var(--c-muted)">
                                            {{ persian_digits(number_format((int) ($plan['price'] ?? 0))) }} تومان
                                            @if (!empty($plan['period'])) · {{ $plan['period'] }} @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($wanted as $field)
                        @php $meta = $allowed[$field]; @endphp
                        <div class="{{ $field === 'bio' ? 'md:col-span-2' : '' }}">
                            <label for="join-{{ $field }}" class="block mb-1 text-sm font-medium">
                                {{ $meta['label'] }}
                                @if ($meta['required']) <span aria-hidden="true">*</span> @endif
                            </label>
                            <input
                                id="join-{{ $field }}"
                                type="{{ $meta['type'] }}"
                                name="{{ $field }}"
                                value="{{ old($field) }}"
                                autocomplete="{{ $meta['autocomplete'] }}"
                                @if ($meta['required']) required @endif
                                @if (in_array($field, ['email', 'phone', 'national_code'], true)) dir="ltr" @endif
                                class="w-full px-3 py-2 focus:outline-none focus:ring-2 focus:ring-(--c-primary)"
                                style="{{ $inputStyle }}"
                            >
                        </div>
                    @endforeach
                </div>

                <button type="submit"
                        class="w-full mt-6 font-semibold rounded-[var(--radius-button)] py-2.5 transition-colors hover:opacity-90"
                        style="background:var(--c-primary);color:var(--c-on-primary)">
                    {{ $j['submit_label'] ?? 'ارسال درخواست' }}
                </button>
            </form>
        </div>
    </div>
</section>
