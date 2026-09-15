@extends('consultant.settings.layout')

{{-- ?tab=password — the hub's password section, now in the edit card's
     structure (hero + grouped icon/label rows). All fields live in the
     shared partial; the current-password rule stays server-side. --}}
@section('settings-content')
@include('partials.profile-password-fields', ['profile' => $user, 'portal' => 'consultant'])
@endsection
