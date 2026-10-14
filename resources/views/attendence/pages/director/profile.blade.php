@extends('attendence.layouts.app')

@section('title', 'Director Profile')
@section('page-title', 'Director Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    @include('attendence.partials.profile-details')
@endsection
