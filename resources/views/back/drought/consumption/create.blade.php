@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau relevé de consommation')

@section('content')
    <x-back.page-header title="Nouveau relevé de consommation" desc="Enregistrer la consommation mensuelle d'une zone." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.consumption-readings.store') }}">
            @csrf
            @include('back.drought.consumption._form')
        </form>
    </div>
@endsection
