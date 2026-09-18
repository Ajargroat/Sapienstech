@extends('consultant.settings.layout')

{{--
    Hub home (no ?tab=): the identity hero comes from the shared layout,
    this section is the Telegram-style body — action tiles for the portal's
    main features (the same destinations the top navigation carries, since
    everything personal now lives behind the profile), info rows, the
    settings list, and logout. The forms themselves moved to their own
    edit section (?tab=edit); this page only *shows* and *routes*.
--}}
@section('settings-content')
@php
    $actions = [
        ['label' => $labels['dashboard'] ?? 'داشبورد', 'icon' => 'fa-gauge', 'url' => route('consultant.dashboard')],
        ['label' => $labels['blog_management'] ?? 'وبلاگ', 'icon' => 'fa-blog', 'url' => route('consultant.blog.index')],
        ['label' => $labels['direct_chat'] ?? 'گفتگو', 'icon' => 'fa-comments', 'url' => route('consultant.direct-chat')],
    ];
@endphp

@include('partials.profile-home', [
    'profile' => $user,
    'portal' => 'consultant',
    'actions' => $actions,
])
@endsection
