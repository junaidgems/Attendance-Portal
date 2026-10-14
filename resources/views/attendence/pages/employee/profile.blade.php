@extends('attendence.layouts.app')

@section('title', 'Employee Profile')
@section('page-title', 'Employee Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    @include('attendence.partials.profile-details')
@endsection
