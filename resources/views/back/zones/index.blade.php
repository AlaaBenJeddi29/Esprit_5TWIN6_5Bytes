@extends('back.layouts.app')

@section('title', 'AquaSecure — Zones')

@section('content')
    @php
        $hasFilters = $search !== '' || $risque !== '' || $tri !== 'nom' || $sens !== 'asc';
    @endphp

    <x-back.page-header title="Zones" desc="Zones géographiques de desserte du réseau d'eau potable.">
        <x-slot:action>
            {{-- Bouton secondaire : rapport global PDF (Gestion 1) --}}
            <a href="{{ route('back.zones.rapport-global') }}" id="btn-rapport-pdf"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
                Rapport PDF
            </a>
            <a href="{{ route('back.zones.create') }}"
               class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                Nouvelle zone
            </a>
        </x-slot:action>
    </x-back.page-header>

    {{-- Bandeau de progression du rapport (la génération IA peut durer jusqu'à 120 s) --}}
    <div id="rapport-generation-message" class="mb-6 hidden items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 dark:border-brand-500/20 dark:bg-brand-500/10">
        <span class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-brand-500 border-t-transparent"></span>
        <p class="text-sm font-medium text-brand-700 dark:text-brand-400">Génération du rapport en cours…</p>
        <p class="text-xs text-brand-600/70 dark:text-brand-400/70">L'analyse IA peut demander jusqu'à 2 minutes sur un PC sans carte graphique.</p>
    </div>

    <script>
        // Téléchargement du rapport PDF via une iframe cachée : la page ne se
        // recharge pas et le message « en cours » est retiré quand le serveur répond.
        document.addEventListener('DOMContentLoaded', function () {
            const bouton = document.getElementById('btn-rapport-pdf');
            const message = document.getElementById('rapport-generation-message');
            if (!bouton || !message) return;

            bouton.addEventListener('click', function (e) {
                e.preventDefault();

                message.classList.remove('hidden');
                message.classList.add('flex');

                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.addEventListener('load', function () {
                    // La réponse (fichier) est arrivée : le téléchargement démarre
                    message.classList.add('hidden');
                    message.classList.remove('flex');
                    setTimeout(() => iframe.remove(), 2000);
                });
                iframe.src = bouton.href;
                document.body.appendChild(iframe);

                // Filet de sécurité si la génération dépasse le time-limit serveur (240 s)
                setTimeout(function () {
                    message.classList.add('hidden');
                    message.classList.remove('flex');
                }, 250000);
            });
        });
    </script>

    {{-- =====================================================================
         BLOC : Zones à traiter en priorité (top 3 par score_zone)
         ===================================================================== --}}
    @if ($zonesPrioritaires->isNotEmpty())
        <div class="mb-6 overflow-hidden rounded-2xl border border-error-200 bg-error-50 dark:border-error-500/20 dark:bg-error-500/10">
            <div class="border-b border-error-100 px-5 py-4 dark:border-error-500/20">
                <h2 class="text-sm font-semibold text-error-700 dark:text-error-400">Zones à traiter en priorité</h2>
                <p class="mt-0.5 text-xs text-error-600/70 dark:text-error-400/70">Classement basé sur le score de zone composite (risque moyen + proportion d'infrastructures critiques).</p>
            </div>
            <div class="divide-y divide-error-100 dark:divide-error-500/20">
                @foreach ($zonesPrioritaires as $rang => $zp)
                    @php
                        /* Badge de score selon le niveau */
                        $scoreBadgeClass = match (true) {
                            $zp->score_zone > 80 => 'bg-error-100 text-error-900 dark:bg-error-500/30 dark:text-error-300',
                            $zp->score_zone > 60 => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                            $zp->score_zone > 30 => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                            default               => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                        };
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="flex items-center gap-3">
                            {{-- Rang --}}
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-error-100 text-xs font-bold text-error-700 dark:bg-error-500/20 dark:text-error-400">
                                {{ $rang + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $zp->nom }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $zp->raison_risque }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $scoreBadgeClass }}">
                                Score {{ number_format($zp->score_zone, 1, ',', ' ') }}
                            </span>
                            <a href="{{ route('back.zones.show', $zp) }}"
                               class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">
                                Voir la fiche →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune zone n'a encore de score de risque calculé. Renseignez les scores des infrastructures pour activer le classement.</p>
        </div>
    @endif

    {{-- =====================================================================
         BARRE DE RECHERCHE + FILTRE RISQUE
         ===================================================================== --}}
    <form method="GET" action="{{ route('back.zones.index') }}" class="mb-5 flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ $search }}" placeholder="Nom, commune ou code postal…"
               class="w-full max-w-xs rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">

        <select name="risque"
                class="rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
            <option value="" @selected($risque === '')>Tous les risques</option>
            <option value="faible"   @selected($risque === 'faible')>Faible</option>
            <option value="modere"   @selected($risque === 'modere')>Modéré</option>
            <option value="eleve"    @selected($risque === 'eleve')>Élevé</option>
            <option value="critique" @selected($risque === 'critique')>Critique</option>
        </select>

        {{-- Conserver les paramètres de tri lors d'une nouvelle recherche --}}
        @if ($tri !== 'nom')
            <input type="hidden" name="tri"  value="{{ $tri }}">
        @endif
        @if ($sens !== 'asc')
            <input type="hidden" name="sens" value="{{ $sens }}">
        @endif
        <input type="hidden" name="per_page" value="{{ $perPage }}">

        <button type="submit"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Filtrer
        </button>
        @if ($hasFilters)
            <a href="{{ route('back.zones.index') }}" class="inline-flex items-center px-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Réinitialiser</a>
        @endif
    </form>

    {{-- =====================================================================
         TABLEAU DES ZONES
         ===================================================================== --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($zones->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">

                            @php
                                /* Helper : URL de tri pour une colonne */
                                $lienTri = fn (string $col) => route('back.zones.index', array_filter([
                                    'search' => $search ?: null,
                                    'risque' => $risque ?: null,
                                    'per_page' => $perPage !== 15 ? $perPage : null,
                                    'tri'    => $col,
                                    'sens'   => ($tri === $col && $sens === 'asc') ? 'desc' : 'asc',
                                ]));
                                /* Flèche directionnelle */
                                $fleche = fn (string $col) => $tri === $col
                                    ? ($sens === 'asc' ? ' ↑' : ' ↓')
                                    : '';
                            @endphp

                            <th class="px-5 py-3 font-medium">
                                <a href="{{ $lienTri('nom') }}" class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Nom{{ $fleche('nom') }}
                                </a>
                            </th>
                            <th class="px-5 py-3 font-medium">Commune</th>
                            <th class="px-5 py-3 font-medium">Code postal</th>
                            <th class="px-5 py-3 font-medium">
                                <a href="{{ $lienTri('population') }}" class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Population{{ $fleche('population') }}
                                </a>
                            </th>
                            <th class="px-5 py-3 font-medium">Infrastructures</th>
                            <th class="px-5 py-3 font-medium">
                                <a href="{{ $lienTri('risque_moyen') }}" class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Risque moyen{{ $fleche('risque_moyen') }}
                                </a>
                            </th>
                            <th class="px-5 py-3 font-medium">
                                <a href="{{ $lienTri('critiques_count') }}" class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Critiques{{ $fleche('critiques_count') }}
                                </a>
                            </th>
                            <th class="px-5 py-3 font-medium">
                                <a href="{{ $lienTri('score_zone') }}" class="hover:text-gray-700 dark:hover:text-gray-200">
                                    Score de zone{{ $fleche('score_zone') }}
                                </a>
                            </th>
                            <th class="px-5 py-3 font-medium">Coût prévu (3 mois)</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($zones as $zone)
                            <tr class="border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $zone->nom }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $zone->commune }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $zone->code_postal }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format($zone->population, 0, ',', ' ') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $zone->infrastructures_count }}</td>

                                {{-- Badge risque moyen --}}
                                <td class="px-5 py-3">
                                    @if ($zone->risque_moyen === null)
                                        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                    @else
                                        @php
                                            $niveauClasses = match ($zone->niveau_risque) {
                                                'faible'   => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                                                'modere'   => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                                                'eleve'    => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                                                'critique' => 'bg-error-100 text-error-900 dark:bg-error-500/30 dark:text-error-300',
                                                default    => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                                            };
                                            $niveauLabel = match ($zone->niveau_risque) {
                                                'faible'   => 'Faible',
                                                'modere'   => 'Modéré',
                                                'eleve'    => 'Élevé',
                                                'critique' => 'Critique',
                                                default    => 'Aucun',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $niveauClasses }}">
                                            {{ $niveauLabel }}
                                            <span class="opacity-70">({{ number_format((float) $zone->risque_moyen, 1, ',', ' ') }})</span>
                                        </span>
                                    @endif
                                </td>

                                {{-- Badge critiques --}}
                                <td class="px-5 py-3">
                                    @if ($zone->critiques_count > 0)
                                        <span class="inline-flex items-center rounded-full bg-error-100 px-2.5 py-0.5 text-xs font-semibold text-error-900 dark:bg-error-500/30 dark:text-error-300">
                                            {{ $zone->critiques_count }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">0</span>
                                    @endif
                                </td>

                                {{-- Badge score de zone --}}
                                <td class="px-5 py-3">
                                    @if ($zone->score_zone === null)
                                        <span class="text-sm text-gray-400 dark:text-gray-500">—</span>
                                    @else
                                        @php
                                            $scoreZone = (float) $zone->score_zone;
                                            $scoreZoneClass = match (true) {
                                                $scoreZone > 80 => 'bg-error-100 text-error-900 dark:bg-error-500/30 dark:text-error-300',
                                                $scoreZone > 60 => 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                                                $scoreZone > 30 => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                                                default         => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $scoreZoneClass }}">
                                            {{ number_format($scoreZone, 1, ',', ' ') }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Coût prévu 3 mois --}}
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    @if (isset($previsionParZone[$zone->id]) && $previsionParZone[$zone->id] !== null)
                                        {{ number_format($previsionParZone[$zone->id], 2, ',', ' ') }} DT
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.zones.show', $zone) }}" class="text-xs font-medium text-brand-500 hover:underline dark:text-brand-400">Voir</a>
                                        <a href="{{ route('back.zones.edit', $zone) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.zones.destroy', $zone)"
                                            message="La zone « {{ $zone->nom }} » sera supprimée (uniquement si elle ne contient aucune infrastructure)." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Légende des couleurs de risque --}}
            <div class="border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                <div class="flex flex-wrap items-center gap-4">
                    <span class="text-xs text-gray-400 dark:text-gray-500">Légende :</span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <span class="h-2.5 w-2.5 rounded-full bg-success-500"></span>Faible (0 – 30)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <span class="h-2.5 w-2.5 rounded-full bg-warning-500"></span>Modéré (31 – 60)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <span class="h-2.5 w-2.5 rounded-full bg-error-500"></span>Élevé (61 – 80)
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <span class="h-2.5 w-2.5 rounded-full bg-error-900 dark:bg-error-400"></span>Critique (81 – 100)
                    </span>
                </div>
            </div>

            <x-back.pagination :paginator="$zones" label="zones" />
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucune zone" :message="$hasFilters ? 'Aucune zone ne correspond aux filtres sélectionnés.' : 'Créez une première zone géographique.'">
                    <x-slot:action>
                        <a href="{{ route('back.zones.create') }}"
                           class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Créer une zone</a>
                    </x-slot:action>
                </x-back.empty-state>
            </div>
        @endif
    </div>

@endsection
