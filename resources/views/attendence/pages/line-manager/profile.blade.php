@extends('attendence.layouts.app')

@section('title', 'Line Manager Profile')
@section('page-title', 'Line Manager Profile')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
    @include('attendence.partials.profile-details')
@endsection
