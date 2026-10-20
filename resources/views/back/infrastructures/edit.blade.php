@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une infrastructure')

@section('content')

    <x-back.page-header title="Modifier l'infrastructure" :desc="$infrastructure->nom" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.infrastructures.update', $infrastructure) }}">
            @csrf
            @method('PUT')
            @include('back.infrastructures._form')
        </form>
    </div>

@endsection
