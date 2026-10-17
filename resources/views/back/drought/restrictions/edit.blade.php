@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier une restriction')

@section('content')
    <x-back.page-header title="Modifier la restriction" :desc="$restriction->titre" />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form method="POST" action="{{ route('back.restrictions.update', $restriction) }}">
            @csrf
            @method('PUT')
            @include('back.drought.restrictions._form')
        </form>
    </div>
@endsection
