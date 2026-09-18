@extends('consultant.settings.layout')

{{--
    ?tab=edit — the account-editing section of the hub. All of the layout
    and fields live in the shared Telegram-style partial (identity header
    with camera badge, then tap-to-edit rows); this view only wires the
    portal's own variables and the dialog behavior bundle.
--}}
@section('settings-content')
@include('partials.profile-edit-fields', ['profile' => $user, 'portal' => 'consultant'])

@vite(['resources/js/features/profile-edit.js'])
@endsection
