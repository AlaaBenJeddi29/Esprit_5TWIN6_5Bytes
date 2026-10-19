@php
    $isEdit = $planification->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }}">
            <option value="">— Sélectionner —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $planification->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_prevue" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date prévue <span class="text-error-500">*</span></label>
        <input type="date" id="date_prevue" name="date_prevue"
               value="{{ old('date_prevue', $planification->date_prevue?->format('Y-m-d')) }}"
               class="{{ $inputClass }}">
        @error('date_prevue')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="frequence" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Fréquence <span class="text-error-500">*</span></label>
        <select id="frequence" name="frequence" class="{{ $inputClass }}">
            @foreach ($frequences as $value => $label)
                <option value="{{ $value }}" @selected(old('frequence', $planification->frequence) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('frequence')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
        <textarea id="notes" name="notes" rows="2" class="{{ $inputClass }}" placeholder="Point de prélèvement, consignes…">{{ old('notes', $planification->notes) }}</textarea>
        @error('notes')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>
</div>

<p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
    La fréquence choisie produit automatiquement le prochain contrôle après l'enregistrement du prélèvement correspondant.
    Un contrôle ne devient jamais « effectué » sans prélèvement.
</p>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Planifier le contrôle' }}
    </button>
    <a href="{{ route('back.quality-planifications.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
