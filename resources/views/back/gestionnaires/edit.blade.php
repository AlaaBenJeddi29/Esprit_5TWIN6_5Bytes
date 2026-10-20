@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un gestionnaire')

@section('content')
    <x-back.page-header
        title="Modifier le gestionnaire"
        :desc="$gestionnaire->name . ' — ' . $gestionnaire->email"
    />

    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <form novalidate method="POST" action="{{ route('back.gestionnaires.update', $gestionnaire) }}">
            @csrf
            @method('PUT')
            @include('back.gestionnaires._form')
        </form>
    </div>
@endsection
