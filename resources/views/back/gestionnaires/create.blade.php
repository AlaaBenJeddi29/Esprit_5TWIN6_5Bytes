@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau gestionnaire')

@section('content')
    <x-back.page-header
        title="Nouveau gestionnaire"
        desc="Créer un compte d'accès gestionnaire au Back Office."
    />

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <form novalidate method="POST" action="{{ route('back.gestionnaires.store') }}">
            @csrf
            @include('back.gestionnaires._form')
        </form>
    </div>
@endsection
