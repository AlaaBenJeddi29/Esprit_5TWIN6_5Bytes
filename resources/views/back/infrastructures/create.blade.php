@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouvelle infrastructure')

@section('content')

    <x-back.page-header title="Nouvelle infrastructure" desc="Ajouter un ouvrage au réseau." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.infrastructures.store') }}">
            @csrf
            @include('back.infrastructures._form')
        </form>
    </div>

@endsection
