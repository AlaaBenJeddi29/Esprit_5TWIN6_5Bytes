@php
    $isEdit = $infrastructure->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="nom" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nom <span class="text-error-500">*</span></label>
        <input type="text" id="nom" name="nom" value="{{ old('nom', $infrastructure->nom) }}"
               class="{{ $inputClass }} @error('nom') border-error-400 dark:border-error-500 @enderror">
        @error('nom')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $infrastructure->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-error-500">*</span></label>
        <select id="type" name="type" class="{{ $inputClass }} @error('type') border-error-400 dark:border-error-500 @enderror">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $infrastructure->type) === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="statut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut <span class="text-error-500">*</span></label>
        <select id="statut" name="statut" class="{{ $inputClass }} @error('statut') border-error-400 dark:border-error-500 @enderror">
            @foreach ($statuts as $statut)
                <option value="{{ $statut }}" @selected(old('statut', $infrastructure->statut) === $statut)>{{ ucfirst(str_replace('_', ' ', $statut)) }}</option>
            @endforeach
        </select>
        @error('statut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="materiau" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Matériau</label>
        <input type="text" id="materiau" name="materiau" value="{{ old('materiau', $infrastructure->materiau) }}"
               class="{{ $inputClass }} @error('materiau') border-error-400 dark:border-error-500 @enderror">
        @error('materiau')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_installation" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date d'installation</label>
        <input type="date" id="date_installation" name="date_installation"
               value="{{ old('date_installation', $infrastructure->date_installation?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_installation') border-error-400 dark:border-error-500 @enderror">
        @error('date_installation')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="capacite" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Capacité</label>
        <input type="text" id="capacite" name="capacite" value="{{ old('capacite', $infrastructure->capacite) }}"
               class="{{ $inputClass }} @error('capacite') border-error-400 dark:border-error-500 @enderror">
        @error('capacite')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="score_risque" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Score de risque</label>
        <input type="text" id="score_risque" name="score_risque" value="{{ old('score_risque', $infrastructure->score_risque) }}"
               class="{{ $inputClass }} @error('score_risque') border-error-400 dark:border-error-500 @enderror">
        @error('score_risque')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="latitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $infrastructure->latitude) }}"
               class="{{ $inputClass }} @error('latitude') border-error-400 dark:border-error-500 @enderror">
        @error('latitude')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="longitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $infrastructure->longitude) }}"
               class="{{ $inputClass }} @error('longitude') border-error-400 dark:border-error-500 @enderror">
        @error('longitude')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : "Créer l'infrastructure" }}
    </button>
    <a href="{{ route('back.infrastructures.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
