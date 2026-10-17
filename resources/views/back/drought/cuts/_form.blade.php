@php
    $isEdit = $cut->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="motif" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Motif <span class="text-error-500">*</span></label>
        <input type="text" id="motif" name="motif" value="{{ old('motif', $cut->motif) }}"
               class="{{ $inputClass }} @error('motif') border-error-400 dark:border-error-500 @enderror">
        @error('motif')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone concernée</label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Aucune —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $cut->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="infrastructure_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure concernée</label>
        <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }} @error('infrastructure_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Aucune —</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id', $cut->infrastructure_id) === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        @error('infrastructure_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_debut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Début <span class="text-error-500">*</span></label>
        <input type="datetime-local" id="date_debut" name="date_debut" value="{{ old('date_debut', $cut->date_debut?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }} @error('date_debut') border-error-400 dark:border-error-500 @enderror">
        @error('date_debut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_fin" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fin <span class="text-error-500">*</span></label>
        <input type="datetime-local" id="date_fin" name="date_fin" value="{{ old('date_fin', $cut->date_fin?->format('Y-m-d\TH:i')) }}"
               class="{{ $inputClass }} @error('date_fin') border-error-400 dark:border-error-500 @enderror">
        @error('date_fin')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="statut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut <span class="text-error-500">*</span></label>
        <select id="statut" name="statut" class="{{ $inputClass }} @error('statut') border-error-400 dark:border-error-500 @enderror">
            @foreach ($statuts as $key => $label)
                <option value="{{ $key }}" @selected(old('statut', $cut->statut) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('statut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la coupure' }}
    </button>
    <a href="{{ route('back.scheduled-cuts.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
