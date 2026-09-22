{{--
    resources/views/public/sections/classrooms/default.blade.php

    Classrooms by grade, read live from the tenant's own hierarchy via
    TenantRoster. A school renders its real الف/ب/ج rows with live student
    counts; a consultancy has no classrooms, so the section drops off its page
    rather than rendering a placeholder table.
--}}
@php
    $c      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $cols   = max(1, (int) ($c['columns'] ?? 3));

    if (($c['source'] ?? 'roster') === 'roster') {
        $grades = \App\Support\TenantRoster::classrooms(tenant());
    } else {
        // Manual rows: one row per classroom, grouped by grade for display.
        $grades = collect(array_filter($c['items'] ?? [], static fn ($i) => $i['visible'] ?? true))
            ->groupBy(static fn ($i) => (string) ($i['grade'] ?? ''))
            ->map(static fn ($rooms, string $grade): array => [
                'grade' => $grade,
                'total' => (int) $rooms->sum(static fn ($r) => (int) ($r['students'] ?? 0)),
                'rooms' => $rooms->map(static fn ($r): array => [
                    'name'     => (string) ($r['name'] ?? ''),
                    'students' => (int) ($r['students'] ?? 0),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
@endphp

@if ($grades !== [])
<section id="{{ $c['id'] ?? 'classrooms' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $c['heading'] ?? null,
            'subheading' => $c['subheading'] ?? null,
            'path'       => 'public.landing.classrooms',
        ])

        <div class="lp-classes {{ $reveal ? 'reveal' : '' }}" style="--cols:{{ $cols }}">
            @foreach ($grades as $grade)
                <article class="lp-card lp-classes__card">
                    <h3 class="lp-classes__grade" style="font-size:var(--h3-size)">پایه {{ $grade['grade'] }}</h3>

                    <ul class="lp-classes__rooms">
                        @foreach ($grade['rooms'] as $room)
                            <li>
                                <span>{{ $room['name'] }}</span>
                                <span class="lp-classes__count" style="color:var(--c-muted)">{{ persian_digits($room['students']) }} نفر</span>
                            </li>
                        @endforeach
                    </ul>

                    <footer class="lp-classes__total" style="color:var(--c-subtle)">
                        مجموعاً {{ persian_digits($grade['total']) }} دانش‌آموز
                    </footer>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
