@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une zone')

@section('content')

    <x-back.page-header title="Modifier la zone" :desc="$zone->nom.' — '.$zone->commune" />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.zones.update', $zone) }}">
            @csrf
            @method('PUT')
            @include('back.zones._form')
        </form>
    </div>

@endsection
