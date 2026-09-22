{{--
    resources/views/public/sections/faculty/default.blade.php

    Staff roster: the people behind the school, read live from the tenant's own
    hierarchy (teachers for a school, consultants for a consultancy) via
    TenantRoster. A consultancy therefore renders its consultants from the same
    template, and a tenant whose staff are not provisioned as users falls back
    to its configured `items` rather than rendering an empty grid.
--}}
@php
    $f       = $cfg;
    $reveal  = $anim['reveal'] ?? true;
    $cols    = max(1, (int) ($f['columns'] ?? 3));

    if (($f['source'] ?? 'roster') === 'roster') {
        $members = \App\Support\TenantRoster::staff(tenant(), (int) ($f['limit'] ?? 12));
    } else {
        $members = array_map(static function (array $item): array {
            return [
                'name'     => (string) ($item['name'] ?? ''),
                'initials' => mb_substr((string) ($item['name'] ?? ''), 0, 1),
                'subject'  => $item['subject'] ?? null,
                'bio'      => $item['bio'] ?? null,
                'avatar'   => $item['avatar'] ?? null,
            ];
        }, array_values(array_filter($f['items'] ?? [], static fn ($i) => $i['visible'] ?? true)));
    }

    $accents = ['primary', 'secondary', 'accent_teal', 'accent_amber', 'accent_blue', 'accent_rose'];
@endphp

@if ($members !== [])
<section id="{{ $f['id'] ?? 'faculty' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $f['heading'] ?? null,
            'subheading' => $f['subheading'] ?? null,
            'path'       => 'public.landing.faculty',
        ])

        <div class="lp-faculty {{ $reveal ? 'reveal' : '' }}"
             style="--cols:{{ $cols }}">
            @foreach ($members as $i => $member)
                @php($accent = $accents[$i % count($accents)])
                <article class="lp-card lp-faculty__card"
                         data-studio-path="public.landing.faculty.items.{{ $i }}">
                    <div class="lp-faculty__head">
                        @if (!empty($member['avatar']))
                            <img src="{{ tenant_asset($member['avatar']) }}" alt="{{ $member['name'] }}" class="lp-faculty__avatar" loading="lazy">
                        @else
                            <span class="lp-faculty__avatar lp-faculty__avatar--initial" style="background:linear-gradient(135deg, var(--c-{{ $accent }}), var(--c-{{ $accents[($i + 2) % count($accents)] }}))">{{ $member['initials'] }}</span>
                        @endif
                        <div>
                            <h3 class="lp-faculty__name" style="font-size:var(--h3-size)">{{ $member['name'] }}</h3>
                            @if (!empty($member['subject']))
                                <p class="lp-faculty__subject" style="color:var(--c-{{ $accent }})">{{ $member['subject'] }}</p>
                            @endif
                        </div>
                    </div>
                    @if (($f['show_bio'] ?? true) && !empty($member['bio']))
                        <p class="lp-faculty__bio" style="color:var(--c-muted)">{{ $member['bio'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
