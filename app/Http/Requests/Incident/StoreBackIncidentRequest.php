<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackIncidentRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(Incident::TYPES))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'infrastructure_id' => ['nullable', 'integer', 'exists:infrastructures,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'severity' => ['required', Rule::in(array_keys(Incident::SEVERITIES))],
            'priority' => ['required', Rule::in(array_keys(Incident::PRIORITIES))],
            // Le statut initial est imposé par le workflow : un incident créé
            // depuis le Back Office démarre toujours à « nouveau ».
            'status' => ['sometimes', Rule::in(['nouveau'])],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->whereIn('role', ['gestionnaire', 'admin']),
            ],
        ];
    }
}
