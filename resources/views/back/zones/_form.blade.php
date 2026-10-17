@php
    $isEdit = $zone->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="nom" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nom de la zone <span class="text-error-500">*</span></label>
        <input type="text" id="nom" name="nom" value="{{ old('nom', $zone->nom) }}"
               class="{{ $inputClass }} @error('nom') border-error-400 dark:border-error-500 @enderror">
        @error('nom')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="commune" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Commune <span class="text-error-500">*</span></label>
        <input type="text" id="commune" name="commune" value="{{ old('commune', $zone->commune) }}"
               class="{{ $inputClass }} @error('commune') border-error-400 dark:border-error-500 @enderror">
        @error('commune')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="code_postal" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code postal <span class="text-error-500">*</span></label>
        <input type="text" id="code_postal" name="code_postal" value="{{ old('code_postal', $zone->code_postal) }}"
               class="{{ $inputClass }} @error('code_postal') border-error-400 dark:border-error-500 @enderror">
        @error('code_postal')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="population" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Population</label>
        <input type="text" id="population" name="population" value="{{ old('population', $zone->population) }}"
               class="{{ $inputClass }} @error('population') border-error-400 dark:border-error-500 @enderror">
        @error('population')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="zone-map-search" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Rechercher une localisation</label>
        <div class="relative">
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" id="zone-map-search" data-zone-map-search autocomplete="off"
                       placeholder="Adresse, commune, lieu…"
                       class="{{ $inputClass }} min-w-0 flex-1">
                <button type="button" data-zone-map-search-button
                        class="mt-1 inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    Rechercher
                </button>
            </div>
            <ul data-zone-map-results role="listbox"
                class="absolute z-20 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"></ul>
        </div>
        <p data-zone-map-status class="mt-2 text-xs text-gray-500 dark:text-gray-400" aria-live="polite">
            Cliquez sur la carte ou recherchez une adresse pour renseigner automatiquement la latitude et la longitude.
        </p>
        <div id="zone-map" data-zone-map data-lat-input="latitude" data-lng-input="longitude"
             class="mt-3 h-72 w-full rounded-lg border border-gray-200 dark:border-gray-700"
             aria-label="Carte de sélection de la zone"></div>
    </div>

    <div>
        <label for="latitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $zone->latitude) }}"
               autocomplete="off"
               class="{{ $inputClass }} @error('latitude') border-error-400 dark:border-error-500 @enderror">
        @error('latitude')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Renseignée par la carte ou saisie manuellement.</p>
    </div>

    <div>
        <label for="longitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $zone->longitude) }}"
               autocomplete="off"
               class="{{ $inputClass }} @error('longitude') border-error-400 dark:border-error-500 @enderror">
        @error('longitude')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Renseignée par la carte ou saisie manuellement.</p>
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la zone' }}
    </button>
    <a href="{{ route('back.zones.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
