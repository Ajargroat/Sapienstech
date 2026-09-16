@extends('student.settings.layout')

{{-- Student ?tab=password — mirrors the consultant password section under
     the student guard (current_password:student), same shared partial. --}}
@section('settings-content')
@include('partials.profile-password-fields', ['profile' => $student, 'portal' => 'student'])
@endsection
