@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle zone')

@section('content')

    <x-back.page-header title="Nouvelle zone" desc="Créer une zone géographique de desserte." />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.zones.store') }}">
            @csrf
            @include('back.zones._form')
        </form>
    </div>

@endsection
