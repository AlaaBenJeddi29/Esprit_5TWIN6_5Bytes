@extends('back.layouts.app')

@section('title', 'AquaSecure — Prélèvement '.$sample->reference)

@section('content')
    @php
        $tone = fn (string $statut) => match ($statut) {
            'conforme' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
            'non_conforme' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
        };
    @endphp

    <x-back.page-header :title="'Prélèvement '.$sample->reference" :desc="$sample->zone?->nom.' · '.$sample->date_prelevement?->format('d/m/Y H:i')">
        <x-slot:action>
            <a href="{{ route('back.quality-samples.edit', $sample) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Modifier</a>
            <x-back.confirm-delete :action="route('back.quality-samples.destroy', $sample)" message="Ce prélèvement et ses alertes seront supprimés." />
        </x-slot:action>
    </x-back.page-header>

    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12 space-y-6 xl:col-span-8">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $tone($sample->statut) }}">{{ $sample->statutLabel() }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Score de conformité : <strong class="text-gray-800 dark:text-white/90">{{ $sample->score !== null ? $sample->score.' %' : '—' }}</strong></span>
                </div>

                <dl class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500 dark:text-gray-400">Infrastructure</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $sample->infrastructure?->nom ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Point de prélèvement</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $sample->source ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Préleveur</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $sample->preleveur?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Enregistré le</dt><dd class="mt-0.5 font-medium text-gray-800 dark:text-white/90">{{ $sample->created_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>

                @if ($sample->notes)
                    <p class="mt-5 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $sample->notes }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-brand-200 bg-brand-50/40 p-5 dark:border-brand-500/20 dark:bg-brand-500/[0.06] md:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Analyse intelligente de la qualité de l'eau</h2>
                    <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-0.5 text-[10px] font-medium uppercase text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">Gemini</span>
                </div>

                @if ($aiSummary)
                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-gray-300">{{ $aiSummary['resume'] }}</p>

                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-800 dark:text-white/90">Anomalies détectées</h3>
                        @if (! empty($aiSummary['anomalies']))
                            <ul class="mt-2 space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                                @foreach ($aiSummary['anomalies'] as $anomaly)
                                    <li class="flex gap-2">
                                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500"></span>
                                        <span>{{ $anomaly }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aucune anomalie détectée par l'analyse intelligente.</p>
                        @endif
                    </div>

                    @if ($aiSummary['genere_le'])
                        <p class="mt-4 text-[11px] text-gray-400">Résumé généré automatiquement le {{ $aiSummary['genere_le'] }}. Il complète l'analyse des seuils sans la remplacer.</p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Le résumé intelligent n'est pas disponible pour ce prélèvement. L'analyse automatique des seuils et les alertes restent affichées ci-dessous.</p>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 md:px-6">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Résultats par paramètre</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="px-5 py-3 font-medium">Paramètre</th>
                                <th class="px-5 py-3 font-medium">Valeur</th>
                                <th class="px-5 py-3 font-medium">Seuil appliqué</th>
                                <th class="px-5 py-3 font-medium">Conformité</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sample->parameters as $parameter)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                    <td class="px-5 py-3 text-sm text-gray-800 dark:text-white/90">{{ $parameter->parametreLabel() }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $parameter->valeur }} {{ $parameter->unite }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $parameter->seuil_min !== null ? 'min '.$parameter->seuil_min : '' }}
                                        {{ $parameter->seuil_min !== null && $parameter->seuil_max !== null ? ' · ' : '' }}
                                        {{ $parameter->seuil_max !== null ? 'max '.$parameter->seuil_max : '' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $parameter->est_conforme ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400' }}">
                                            {{ $parameter->est_conforme ? 'Conforme' : 'Non conforme' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">Aucun paramètre analysé.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-span-12 xl:col-span-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-medium text-gray-800 dark:text-white/90">Alertes générées</h2>
                    <a href="{{ route('back.quality-alerts.index') }}" class="text-xs font-medium text-brand-500 hover:underline">Toutes les alertes</a>
                </div>
                <ul class="mt-3 space-y-3">
                    @forelse ($sample->alerts as $alert)
                        <li class="rounded-xl border border-gray-100 p-3 text-sm dark:border-gray-800">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $alert->parametre }}</span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase {{ $alert->statut === 'active' ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-400' : 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400' }}">{{ $alert->statutLabel() }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $alert->message }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500 dark:text-gray-400">Aucune alerte pour ce prélèvement.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
