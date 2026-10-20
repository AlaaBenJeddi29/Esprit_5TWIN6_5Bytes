@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un seuil')

@section('content')
    <x-back.page-header title="Modifier le seuil" :desc="$threshold->parametreLabel().' — '.$threshold->conformiteLabel()" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.quality-thresholds.update', $threshold) }}">
            @csrf
            @method('PUT')
            @include('back.quality.thresholds._form')
        </form>
    </div>
@endsection
