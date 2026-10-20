@extends('back.layouts.app')

@section('title', 'AquaSecure — Nouveau projet')

@section('content')

    <x-back.page-header title="Nouveau projet" desc="Déclarer un projet d'investissement sur le réseau." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.projects.store') }}">
            @csrf
            @include('back.projects._form')
        </form>
    </div>

@endsection
