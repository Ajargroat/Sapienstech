@extends('student.settings.layout')

{{--
    Student ?tab=edit — same shared partial as the consultant's edit
    section. Grade and major stay consultant-managed, so they appear on the
    profile (hero status line) but are deliberately not editable rows here.
--}}
@section('settings-content')
@include('partials.profile-edit-fields', ['profile' => $student, 'portal' => 'student'])

@vite(['resources/js/features/profile-edit.js'])
@endsection
