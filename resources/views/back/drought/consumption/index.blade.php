@extends('back.layouts.app')

@section('title', 'AquaSecure — Relevés de consommation')

@section('content')
    @php
        $inputClass = 'rounded-lg border-gray-300 bg-white text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    @endphp

    <x-back.page-header title="Consommations mensuelles" desc="Relevés utilisés pour la prévision de la demande et le risque sécheresse.">
        <x-slot:action>
            <a href="{{ route('back.consumption-readings.forecast') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Voir les prévisions</a>
            <a href="{{ route('back.consumption-readings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Nouveau relevé</a>
        </x-slot:action>
    </x-back.page-header>

    <form method="GET" class="mb-5 flex flex-wrap gap-2">
        <select name="zone_id" class="{{ $inputClass }}">
            <option value="">Toutes les zones</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected($currentZone === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">Filtrer</button>
        <a href="{{ route('back.consumption-readings.export', array_merge(request()->only(['zone_id']), ['format' => 'pdf'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📄 Exporter PDF</a>
        <a href="{{ route('back.consumption-readings.export', array_merge(request()->only(['zone_id']), ['format' => 'excel'])) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">📊 Exporter Excel</a>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($readings->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3 font-medium">Zone</th>
                            <th class="px-5 py-3 font-medium">Période</th>
                            <th class="px-5 py-3 font-medium">Consommation</th>
                            <th class="px-5 py-3 font-medium">Abonnés</th>
                            <th class="px-5 py-3 font-medium">m³ / abonné</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($readings as $reading)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.02]">
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-white/90">{{ $reading->zone?->nom ?? '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $reading->periodeLabel() }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ number_format((float) $reading->consommation_m3, 2, ',', ' ') }} m³</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $reading->nombre_abonnes !== null ? number_format($reading->nombre_abonnes, 0, ',', ' ') : '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $reading->nombre_abonnes > 0 ? number_format((float) $reading->consommation_m3 / $reading->nombre_abonnes, 2, ',', ' ') : '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('back.consumption-readings.edit', $reading) }}" class="text-xs font-medium text-gray-600 hover:underline dark:text-gray-400">Modifier</a>
                                        <x-back.confirm-delete :action="route('back.consumption-readings.destroy', $reading)" message="Ce relevé de consommation sera définitivement supprimé." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $readings->links() }}</div>
        @else
            <div class="p-5">
                <x-back.empty-state title="Aucun relevé de consommation" message="Ajoutez au moins deux relevés par zone pour activer les prévisions." />
            </div>
        @endif
    </div>
@endsection
