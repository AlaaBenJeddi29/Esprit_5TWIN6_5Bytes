@extends('back.layouts.app')

@section('title', 'AquaSecure — Maintenance')

@section('content')

    <x-back.page-header :title="$maintenance->type"
        :desc="'Infrastructure : '.($maintenance->infrastructure?->nom ?? '—')">
        <x-slot:action>
            <a href="{{ route('back.maintenances.edit', $maintenance) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.maintenances.destroy', $maintenance)"
                message="Cette maintenance sera définitivement supprimée." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-back.stat-card label="Date d'intervention" :value="$maintenance->date_intervention?->format('d/m/Y') ?? '—'" />
        <x-back.stat-card label="Coût" :value="$maintenance->cout !== null ? number_format($maintenance->cout, 2, ',', ' ').' €' : '—'" />
        <x-back.stat-card label="Statut" :value="$maintenance->statut" />
        <x-back.stat-card label="Technicien" :value="$maintenance->technicien?->name ?? 'Non assigné'" />
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Description</h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $maintenance->description ?: 'Aucune description.' }}</p>

        @if ($maintenance->infrastructure?->zone)
            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
                Zone : {{ $maintenance->infrastructure->zone->nom }} ({{ $maintenance->infrastructure->zone->commune }})
            </p>
        @endif
    </div>

@endsection
