@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un relevé de consommation')

@section('content')
    <x-back.page-header title="Modifier le relevé" :desc="$reading->zone?->nom.' — '.$reading->periodeLabel()" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.consumption-readings.update', $reading) }}">
            @csrf
            @method('PUT')
            @include('back.drought.consumption._form')
        </form>
    </div>
@endsection
