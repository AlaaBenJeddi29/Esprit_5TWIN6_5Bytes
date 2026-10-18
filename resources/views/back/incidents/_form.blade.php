@php
    $isEdit = $incident->exists;
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="reference" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Référence</label>
        <input type="text" id="reference" value="{{ $incident->reference ?? 'Générée automatiquement' }}" disabled class="{{ $inputClass }} bg-gray-50 dark:bg-white/[0.02]">
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type <span class="text-error-500">*</span></label>
        <select id="type" name="type" class="{{ $inputClass }} @error('type') border-error-400 @enderror">
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $incident->type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Titre <span class="text-error-500">*</span></label>
        <input type="text" id="title" name="title" value="{{ old('title', $incident->title) }}" class="{{ $inputClass }} @error('title') border-error-400 @enderror">
        @error('title')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description <span class="text-error-500">*</span></label>
        <textarea id="description" name="description" rows="4" class="{{ $inputClass }} @error('description') border-error-400 @enderror">{{ old('description', $incident->description) }}</textarea>
        @error('description')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="zone_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Zone</label>
        <select id="zone_id" name="zone_id" class="{{ $inputClass }}">
            <option value="">— Aucune —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone->id }}" @selected((int) old('zone_id', $incident->zone_id) === $zone->id)>{{ $zone->nom }}</option>
            @endforeach
        </select>
        @error('zone_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="infrastructure_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Infrastructure</label>
        <select id="infrastructure_id" name="infrastructure_id" class="{{ $inputClass }}">
            <option value="">— Aucune —</option>
            @foreach ($infrastructures as $infrastructure)
                <option value="{{ $infrastructure->id }}" @selected((int) old('infrastructure_id', $incident->infrastructure_id) === $infrastructure->id)>{{ $infrastructure->nom }}</option>
            @endforeach
        </select>
        @error('infrastructure_id')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse</label>
        <input type="text" id="address" name="address" value="{{ old('address', $incident->address) }}" class="{{ $inputClass }}">
        @error('address')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="latitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Latitude</label>
        <input type="text" id="latitude" name="latitude" value="{{ old('latitude', $incident->latitude) }}" class="{{ $inputClass }}">
        @error('latitude')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="longitude" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Longitude</label>
        <input type="text" id="longitude" name="longitude" value="{{ old('longitude', $incident->longitude) }}" class="{{ $inputClass }}">
        @error('longitude')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="severity" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gravité <span class="text-error-500">*</span></label>
        <select id="severity" name="severity" class="{{ $inputClass }}">
            @foreach ($severities as $key => $label)
                <option value="{{ $key }}" @selected(old('severity', $incident->severity) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('severity')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="priority" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Priorité <span class="text-error-500">*</span></label>
        <select id="priority" name="priority" class="{{ $inputClass }}">
            @foreach ($priorities as $key => $label)
                <option value="{{ $key }}" @selected(old('priority', $incident->priority) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('priority')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Statut</label>
        <div class="mt-1 flex items-center gap-2">
            <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                {{ $incident->statusLabel() }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">Géré depuis la fiche via le workflow</span>
        </div>
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
            Le statut ne peut pas être modifié ici : utilisez le formulaire de transition sur la fiche de l'incident.
        </p>
    </div>

    <div>
        <label for="assigned_to" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Assigné à</label>
        <select id="assigned_to" name="assigned_to" class="{{ $inputClass }}">
            <option value="">— Non assigné —</option>
            @foreach ($gestionnaires as $agent)
                <option value="{{ $agent->id }}" @selected((int) old('assigned_to', $incident->assigned_to) === $agent->id)>{{ $agent->name }} ({{ $agent->role?->label() }})</option>
            @endforeach
        </select>
        @error('assigned_to')<p class="mt-1 text-xs text-error-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
        {{ $isEdit ? 'Enregistrer les modifications' : "Créer l'incident" }}
    </button>
    <a href="{{ route('back.incidents.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">Annuler</a>
</div>
