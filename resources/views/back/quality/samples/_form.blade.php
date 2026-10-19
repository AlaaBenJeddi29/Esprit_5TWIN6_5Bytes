@php
    $isEdit = $sample->exists;
    $planification = $planification ?? null;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';
@endphp

@if ($planification)
    <input type="hidden" name="planification_id" value="{{ $planification->id }}">

    <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
        Prélèvement du contrôle planifié du {{ $planification->date_prevue->format('d/m/Y') }}
        ({{ $planification->frequenceLabel() }}) — zone {{ $planification->zone->nom }}.
        Il ne sera marqué « effectué » qu'après l'enregistrement de ce prélèvement.
    </div>
@endif

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }}">
            <option value="">— Sélectionner —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $sample->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="infrastructure_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure</label>
        <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }}">
            <option value="">— Aucune —</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id', $sample->infrastructure_id) === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        @error('infrastructure_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_prelevement" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date du prélèvement <span class="text-error-500">*</span></label>
        <input type="datetime-local" id="date_prelevement" name="date_prelevement"
               value="{{ old('date_prelevement', $sample->date_prelevement?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }}">
        @error('date_prelevement')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="source" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Point de prélèvement</label>
        <input type="text" id="source" name="source" value="{{ old('source', $sample->source) }}" placeholder="Réservoir, robinet, station…" class="{{ $inputClass }}">
        @error('source')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
        <textarea id="notes" name="notes" rows="2" class="{{ $inputClass }}">{{ old('notes', $sample->notes) }}</textarea>
        @error('notes')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Paramètres mesurés</h3>
        <span class="text-xs text-gray-500 dark:text-gray-400">Laissez vide un paramètre non mesuré.</span>
    </div>

    @error('parametres')<p class="mt-2 text-xs text-error-600">{{ $message }}</p>@enderror

    <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs uppercase text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    <th class="px-4 py-2.5 font-medium">Paramètre</th>
                    <th class="px-4 py-2.5 font-medium">Valeur</th>
                    <th class="px-4 py-2.5 font-medium">Unité</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($parametres as $key => $label)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</td>
                        <td class="px-4 py-2.5">
                            <input type="text" name="parametres[{{ $key }}][valeur]"
                                   value="{{ old('parametres.'.$key.'.valeur', $existing->get($key)?->valeur) }}"
                                   class="block w-36 rounded-lg border-gray-300 bg-transparent text-sm dark:border-gray-700 dark:text-white/90">
                            @error('parametres.'.$key.'.valeur')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ $unites[$key] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer et réanalyser' : 'Enregistrer le prélèvement' }}
    </button>
    <a href="{{ route('back.quality-samples.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
