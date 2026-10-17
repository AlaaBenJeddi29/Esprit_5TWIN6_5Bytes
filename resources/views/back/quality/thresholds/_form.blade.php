@php
    $isEdit = $threshold->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="parametre" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Paramètre <span class="text-error-500">*</span></label>
        <select id="parametre" name="parametre" class="{{ $inputClass }}">
            @foreach ($parametres as $key => $label)
                <option value="{{ $key }}" @selected(old('parametre', $threshold->parametre) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('parametre')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone (vide = seuil global)</label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }}">
            <option value="">— Seuil global —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $threshold->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="unite" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unité <span class="text-error-500">*</span></label>
        <input type="text" id="unite" name="unite" value="{{ old('unite', $threshold->unite) }}" list="unites" class="{{ $inputClass }}">
        <datalist id="unites">
            @foreach (array_unique(array_values($unites)) as $unite)
                <option value="{{ $unite }}"></option>
            @endforeach
        </datalist>
        @error('unite')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="actif" value="1" @checked(old('actif', $threshold->actif ?? true)) class="rounded border-gray-300 text-brand-500">
            Seuil actif
        </label>
        @error('actif')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="valeur_min" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valeur minimale</label>
        <input type="text" id="valeur_min" name="valeur_min" value="{{ old('valeur_min', $threshold->valeur_min) }}" class="{{ $inputClass }}">
        @error('valeur_min')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="valeur_max" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Valeur maximale</label>
        <input type="text" id="valeur_max" name="valeur_max" value="{{ old('valeur_max', $threshold->valeur_max) }}" class="{{ $inputClass }}">
        @error('valeur_max')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le seuil' }}
    </button>
    <a href="{{ route('back.quality-thresholds.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
