@php
    $isEdit = $project->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="titre" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Titre du projet <span class="text-error-500">*</span></label>
        <input type="text" id="titre" name="titre" value="{{ old('titre', $project->titre) }}"
               class="{{ $inputClass }} @error('titre') border-error-400 dark:border-error-500 @enderror">
        @error('titre')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-error-500">*</span></label>
        <select id="type" name="type" class="{{ $inputClass }} @error('type') border-error-400 dark:border-error-500 @enderror">
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $project->type) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="statut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut <span class="text-error-500">*</span></label>
        <select id="statut" name="statut" class="{{ $inputClass }} @error('statut') border-error-400 dark:border-error-500 @enderror">
            @foreach ($statuts as $statut)
                <option value="{{ $statut }}" @selected(old('statut', $project->statut) === $statut)>{{ ucfirst(str_replace('_', ' ', $statut)) }}</option>
            @endforeach
        </select>
        @error('statut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description <span class="text-error-500">*</span></label>
        <textarea id="description" name="description" rows="3"
                  class="{{ $inputClass }} @error('description') border-error-400 dark:border-error-500 @enderror">{{ old('description', $project->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="budget_prevu" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Budget prévu (€) <span class="text-error-500">*</span></label>
        <input type="text" id="budget_prevu" name="budget_prevu" value="{{ old('budget_prevu', $project->budget_prevu) }}"
               class="{{ $inputClass }} @error('budget_prevu') border-error-400 dark:border-error-500 @enderror">
        @error('budget_prevu')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="avancement_pourcentage" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Avancement (%) <span class="text-error-500">*</span></label>
        <input type="text" id="avancement_pourcentage" name="avancement_pourcentage"
               value="{{ old('avancement_pourcentage', $project->avancement_pourcentage ?? 0) }}"
               class="{{ $inputClass }} @error('avancement_pourcentage') border-error-400 dark:border-error-500 @enderror">
        @error('avancement_pourcentage')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_debut" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de début <span class="text-error-500">*</span></label>
        <input type="date" id="date_debut" name="date_debut" value="{{ old('date_debut', $project->date_debut?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_debut') border-error-400 dark:border-error-500 @enderror">
        @error('date_debut')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="date_fin_prevue" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de fin prévue <span class="text-error-500">*</span></label>
        <input type="date" id="date_fin_prevue" name="date_fin_prevue" value="{{ old('date_fin_prevue', $project->date_fin_prevue?->format('Y-m-d')) }}"
               class="{{ $inputClass }} @error('date_fin_prevue') border-error-400 dark:border-error-500 @enderror">
        @error('date_fin_prevue')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone <span class="text-error-500">*</span></label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }} @error('zone_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $project->zone_id) === $zone->id)>{{ $zone->nom }} ({{ $zone->commune }})</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="infrastructure_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure liée <span class="font-normal text-gray-400">(facultatif)</span></label>
        <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }} @error('infrastructure_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Aucune —</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id', $project->infrastructure_id) === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        @error('infrastructure_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="responsable_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsable <span class="text-error-500">*</span></label>
        <select id="responsable_id" name="responsable_id" class="{{ $inputClass }} @error('responsable_id') border-error-400 dark:border-error-500 @enderror">
            <option value="">— Sélectionner —</option>
            @foreach ($responsables as $responsable)
                <option value="{{ $responsable->id }}" @selected((int) old('responsable_id', $project->responsable_id) === $responsable->id)>{{ $responsable->name }} ({{ $responsable->role?->label() }})</option>
            @endforeach
        </select>
        @error('responsable_id')<p class="mt-1 text-xs text-error-600 dark:text-error-400">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit"
            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le projet' }}
    </button>
    <a href="{{ $isEdit ? route('back.projects.show', $project) : route('back.projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
        Annuler
    </a>
</div>
