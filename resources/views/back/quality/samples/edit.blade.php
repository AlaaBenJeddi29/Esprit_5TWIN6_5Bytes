@extends('back.layouts.app')

@section('title', 'AquaSecure — Modifier un prélèvement')

@section('content')
    <x-back.page-header :title="'Modifier '.$sample->reference" desc="La modification relance l'analyse de conformité et remplace les alertes actives." />

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <form novalidate method="POST" action="{{ route('back.quality-samples.update', $sample) }}">
            @csrf
            @method('PUT')
            @include('back.quality.samples._form')
        </form>
    </div>
@endsection
