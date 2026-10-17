@extends('back.layouts.app')

@section('title', 'AquaSecure — Gestionnaires')

@section('content')
    <x-back.page-header
        title="Comptes gestionnaires"
        desc="Création et gestion des comptes d'accès au Back Office."
    >
        <x-slot:action>
            <a href="{{ route('back.gestionnaires.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4.5v15m7.5-7.5h-15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Nouveau gestionnaire
            </a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" action="{{ route('back.gestionnaires.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="Rechercher par nom ou email…"
            class="w-full max-w-sm rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
        >
        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Rechercher
        </button>
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($gestionnaires->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Nom</th>
                            <th class="px-5 py-3 font-medium">Email</th>
                            <th class="px-5 py-3 font-medium">Statut</th>
                            <th class="px-5 py-3 font-medium">Créé le</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gestionnaires as $gestionnaire)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white/90">{{ $gestionnaire->name }}</td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $gestionnaire->email }}</td>
                                <td class="px-5 py-3.5">
                                    @if ($gestionnaire->is_active)
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/15 dark:text-success-400 dark:ring-success-500/20">Actif</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-gray-600/30">Désactivé</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">{{ $gestionnaire->created_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.gestionnaires.edit', $gestionnaire) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">
                                            Modifier
                                        </a>
                                        <form method="POST" action="{{ route('back.gestionnaires.toggle', $gestionnaire) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-xs font-medium text-gray-500 hover:underline dark:text-gray-400">
                                                {{ $gestionnaire->is_active ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">
                {{ $gestionnaires->links() }}
            </div>
        @else
            <div class="p-5">
                <x-back.empty-state
                    title="Aucun gestionnaire"
                    message="Créez un premier compte gestionnaire pour lui donner accès au Back Office."
                >
                    <x-slot:action>
                        <a href="{{ route('back.gestionnaires.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                            Créer un gestionnaire
                        </a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>
@endsection
