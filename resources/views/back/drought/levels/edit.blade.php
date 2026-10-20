@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une mesure de niveau')

@section('content')
    <x-back.page-header title="Modifier la mesure de niveau" :desc="$level->nom_source" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.water-levels.update', $level) }}">
            @csrf
            @method('PUT')
            @include('back.drought.levels._form')
        </form>
    </div>
@endsection
