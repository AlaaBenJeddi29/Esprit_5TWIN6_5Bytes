@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un incident')

@section('content')
    <x-back.page-header :title="'Modifier '.$incident->reference" desc="Mettre à jour les informations de l'incident." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.incidents.update', $incident) }}">
            @csrf
            @method('PUT')
            @include('back.incidents._form')
        </form>
    </div>
@endsection
