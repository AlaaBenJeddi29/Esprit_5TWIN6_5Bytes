@php
    $isEdit = $reading->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $reading->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="periode" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Période (mois) <span class="text-error-500">*</span></label>
        <input type="date" id="periode" name="periode" value="{{ old('periode', $reading->periode?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('periode') border-error-400 dark:border-error-500 @enderror">
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Un seul relevé par zone et par mois.</p>
        @error('periode')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="consommation_m3" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Consommation (m³) <span class="text-error-500">*</span></label>
        <input type="text" id="consommation_m3" name="consommation_m3" value="{{ old('consommation_m3', $reading->consommation_m3) }}"
               class="{{ $inputClass }} @error('consommation_m3') border-error-400 dark:border-error-500 @enderror">
        @error('consommation_m3')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="nombre_abonnes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre d'abonnés</label>
        <input type="text" id="nombre_abonnes" name="nombre_abonnes" value="{{ old('nombre_abonnes', $reading->nombre_abonnes) }}"
               class="{{ $inputClass }} @error('nombre_abonnes') border-error-400 dark:border-error-500 @enderror">
        @error('nombre_abonnes')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Enregistrer le relevé' }}
    </button>
    <a href="{{ route('back.consumption-readings.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
