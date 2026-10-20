{{--
    Formulaire réutilisable de signalement (création / modification).
    Variables attendues : $report, $types, $statuses, $action, $method ('POST'|'PUT'), $submitLabel, $isEdit
--}}
@php
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 bg-transparent text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90 dark:focus:border-brand-800';
@endphp

<form novalidate method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    {{-- Type --}}
    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Type <span class="text-error-500">*</span>
        </label>
        <select id="type" name="type" class="{{ $inputClass }}">
            <option value="">— Sélectionner —</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(old('type', $report->type) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Titre --}}
    <div>
        <label for="title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Titre <span class="text-error-500">*</span>
        </label>
        <input type="text" id="title" name="title" value="{{ old('title', $report->title) }}" class="{{ $inputClass }}" />
        @error('title')
            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Description --}}
    <div>
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Description <span class="text-error-500">*</span>
        </label>
        <textarea id="description" name="description" rows="5" class="{{ $inputClass }}">{{ old('description', $report->description) }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Adresse --}}
    <div>
        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse (facultatif)</label>
        <input type="text" id="address" name="address" value="{{ old('address', $report->address) }}" class="{{ $inputClass }}" />
        @error('address')
            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Date de signalement --}}
    <div>
        <label for="reported_at" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date de signalement (facultatif)</label>
        <input type="datetime-local" id="reported_at" name="reported_at" value="{{ old('reported_at', optional($report->reported_at)->format('Y-m-d\TH:i')) }}" class="{{ $inputClass }}" />
        @error('reported_at')
            <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Statut (modification uniquement) --}}
    @if ($isEdit)
        <div>
            <label for="status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Statut <span class="text-error-500">*</span>
            </label>
            <select id="status" name="status" class="{{ $inputClass }}">
                @foreach ($statuses as $key => $label)
                    <option value="{{ $key }}" @selected(old('status', $report->status) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('back.reports.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-300">
            Annuler
        </a>
    </div>
</form>
