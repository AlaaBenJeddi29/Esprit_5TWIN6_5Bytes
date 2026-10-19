<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use App\Services\IncidentClusterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentMapFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessBackOffice() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'severity' => ['nullable', 'string', Rule::in(array_keys(Incident::SEVERITIES))],
            'status' => ['nullable', 'string', Rule::in(array_keys(Incident::STATUSES))],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')],
            'infrastructure_id' => ['nullable', 'integer', Rule::exists('infrastructures', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => $this->filled('date_from')
                ? ['nullable', 'date', 'after_or_equal:date_from']
                : ['nullable', 'date'],
            'radius' => [
                'nullable',
                'integer',
                'min:'.IncidentClusterService::MIN_RADIUS,
                'max:'.IncidentClusterService::MAX_RADIUS,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'severity.in' => 'La gravité sélectionnée est invalide.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'zone_id.exists' => 'La zone sélectionnée est introuvable.',
            'infrastructure_id.exists' => "L'infrastructure sélectionnée est introuvable.",
            'date_from.date' => 'La date de début est invalide.',
            'date_to.date' => 'La date de fin est invalide.',
            'date_to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'radius.integer' => 'Le rayon doit être un entier.',
            'radius.min' => 'Le rayon doit être au moins de '.IncidentClusterService::MIN_RADIUS.' mètres.',
            'radius.max' => 'Le rayon ne peut pas dépasser '.IncidentClusterService::MAX_RADIUS.' mètres.',
        ];
    }
}
