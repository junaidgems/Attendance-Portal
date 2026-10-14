@extends('attendence.layouts.app')

@section('title', 'Manager Profile')
@section('page-title', 'Manager Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    @include('attendence.partials.profile-details')
@endsection
