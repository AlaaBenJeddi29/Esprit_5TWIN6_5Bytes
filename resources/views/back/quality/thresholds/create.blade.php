@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau seuil')

@section('content')
    <x-back.page-header title="Nouveau seuil de qualité" desc="Définir une borne globale ou spécifique à une zone." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.quality-thresholds.store') }}">
            @csrf
            @include('back.quality.thresholds._form')
        </form>
    </div>
@endsection
