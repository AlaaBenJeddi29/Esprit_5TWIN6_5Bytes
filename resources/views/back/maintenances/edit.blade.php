@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une maintenance')

@section('content')

    <x-back.page-header title="Modifier la maintenance" :desc="$maintenance->type" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.maintenances.update', $maintenance) }}">
            @csrf
            @method('PUT')
            @include('back.maintenances._form')
        </form>
    </div>

@endsection
