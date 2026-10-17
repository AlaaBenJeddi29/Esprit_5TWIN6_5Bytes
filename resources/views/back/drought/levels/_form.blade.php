@php
    $isEdit = $level->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="nom_source" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Source <span class="text-error-500">*</span></label>
        <input type="text" id="nom_source" name="nom_source" value="{{ old('nom_source', $level->nom_source) }}"
               class="{{ $inputClass }} @error('nom_source') border-error-400 dark:border-error-500 @enderror">
        @error('nom_source')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type_source" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type de source <span class="text-error-500">*</span></label>
        <select id="type_source" name="type_source" class="{{ $inputClass }} @error('type_source') border-error-400 dark:border-error-500 @enderror">
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type_source', $level->type_source) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type_source')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone</label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Aucune —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $level->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_mesure" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de mesure <span class="text-error-500">*</span></label>
        <input type="datetime-local" id="date_mesure" name="date_mesure"
               value="{{ old('date_mesure', $level->date_mesure?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }} @error('date_mesure') border-error-400 dark:border-error-500 @enderror">
        @error('date_mesure')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="niveau" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Niveau (%) <span class="text-error-500">*</span></label>
        <input type="text" id="niveau" name="niveau" value="{{ old('niveau', $level->niveau) }}"
               class="{{ $inputClass }} @error('niveau') border-error-400 dark:border-error-500 @enderror">
        @error('niveau')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="seuil_alerte" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Seuil d'alerte (%) <span class="text-error-500">*</span></label>
            <input type="text" id="seuil_alerte" name="seuil_alerte" value="{{ old('seuil_alerte', $level->seuil_alerte ?? 30) }}"
                   class="{{ $inputClass }} @error('seuil_alerte') border-error-400 dark:border-error-500 @enderror">
            @error('seuil_alerte')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="seuil_critique" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Seuil critique (%) <span class="text-error-500">*</span></label>
            <input type="text" id="seuil_critique" name="seuil_critique" value="{{ old('seuil_critique', $level->seuil_critique ?? 15) }}"
                   class="{{ $inputClass }} @error('seuil_critique') border-error-400 dark:border-error-500 @enderror">
            @error('seuil_critique')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Enregistrer la mesure' }}
    </button>
    <a href="{{ route('back.water-levels.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
