@php
    $isEdit = $restriction->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="titre" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Titre <span class="text-error-500">*</span></label>
        <input type="text" id="titre" name="titre" value="{{ old('titre', $restriction->titre) }}"
               class="{{ $inputClass }} @error('titre') border-error-400 dark:border-error-500 @enderror">
        @error('titre')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" required class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionnez une zone —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $restriction->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Niveau <span class="text-error-500">*</span></label>
        <select id="type" name="type" class="{{ $inputClass }} @error('type') border-error-400 dark:border-error-500 @enderror">
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $restriction->type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="statut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut <span class="text-error-500">*</span></label>
        <select id="statut" name="statut" class="{{ $inputClass }} @error('statut') border-error-400 dark:border-error-500 @enderror">
            @foreach ($statuts as $key => $label)
                <option value="{{ $key }}" @selected(old('statut', $restriction->statut) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('statut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_debut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Début <span class="text-error-500">*</span></label>
        <input type="datetime-local" id="date_debut" name="date_debut" value="{{ old('date_debut', $restriction->date_debut?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }} @error('date_debut') border-error-400 dark:border-error-500 @enderror">
        @error('date_debut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_fin" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fin</label>
        <input type="datetime-local" id="date_fin" name="date_fin" value="{{ old('date_fin', $restriction->date_fin?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }} @error('date_fin') border-error-400 dark:border-error-500 @enderror">
        @error('date_fin')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
        <textarea id="description" name="description" rows="3" class="{{ $inputClass }} @error('description') border-error-400 dark:border-error-500 @enderror">{{ old('description', $restriction->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="usages_interdits" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Usages interdits</label>
        <textarea id="usages_interdits" name="usages_interdits" rows="3" placeholder="Un usage par ligne (arrosage, lavage, piscine…)" class="{{ $inputClass }} @error('usages_interdits') border-error-400 dark:border-error-500 @enderror">{{ old('usages_interdits', $restriction->usages_interdits) }}</textarea>
        @error('usages_interdits')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la restriction' }}
    </button>
    <a href="{{ route('back.restrictions.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
