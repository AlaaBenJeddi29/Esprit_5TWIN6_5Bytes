@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une entreprise')

@section('content')

    <x-back.page-header title="Modifier l'entreprise" :desc="$contractor->nom.' — '.$contractor->specialite" />

    <div class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.contractors.update', $contractor) }}">
            @csrf
            @method('PUT')
            @include('back.contractors._form')
        </form>
    </div>

@endsection
