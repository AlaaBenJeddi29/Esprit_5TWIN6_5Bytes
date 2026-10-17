@extends('back.layouts.app')

@section('title', 'AquaSecure — Entreprise '.$contractor->nom)

@section('content')

    <x-back.page-header :title="$contractor->nom" :desc="$contractor->specialite">
        <x-slot:action>
            <a href="{{ route('back.contractors.edit', $contractor) }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Modifier
            </a>
            <x-back.confirm-delete :action="route('back.contractors.destroy', $contractor)"
                message="L'entreprise « {{ $contractor->nom }} » sera supprimée." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-back.stat-card label="Email" :value="$contractor->email" />
        <x-back.stat-card label="Téléphone" :value="$contractor->telephone" />
        <x-back.stat-card label="Phases de projet" :value="$contractor->projectPhases->count()" />
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Adresse</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $contractor->adresse }}</p>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Phases attribuées</h2>
        </div>

        @if ($contractor->projectPhases->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Phase</th>
                            <th class="px-5 py-3 font-medium">Projet</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Coût</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contractor->projectPhases as $phase)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $phase->nom }}</td>
                                <td class="px-5 py-3">
                                    @if ($phase->project)
                                        <a href="{{ route('back.projects.show', $phase->project) }}" class="text-sm font-medium text-brand-500 hover:underline dark:text-brand-400">{{ $phase->project->titre }}</a>
                                    @else
                                        <span class="text-sm text-gray-500 dark:text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $phase->date_debut?->format('d/m/Y') }} → {{ $phase->date_fin?->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $phase->cout, 2, ',', ' ') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune phase" message="Cette entreprise n'est affectée à aucune phase de projet." />
            </div>
        @endif
    </div>

@endsection
