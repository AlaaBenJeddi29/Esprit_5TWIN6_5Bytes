@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un financement')

@section('content')

    <x-back.page-header title="Modifier le financement" :desc="'Projet : '.$project->titre" />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.projects.fundings.update', [$project, $funding]) }}">
            @csrf
            @method('PUT')
            @include('back.projects.fundings._form')
        </form>
    </div>

@endsection
