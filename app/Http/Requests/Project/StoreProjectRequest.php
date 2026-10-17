<?php

namespace App\Http\Requests\Project;

use App\Enums\UserRole;
use App\Http\Requests\BackOfficeRequest;
use App\Models\Project\Project;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(Project::TYPES)],
            'description' => ['required', 'string'],
            'budget_prevu' => ['required', 'numeric', 'min:0'],
            'date_debut' => ['required', 'date'],
            'date_fin_prevue' => ['required', 'date', 'after_or_equal:date_debut'],
            'statut' => ['required', Rule::in(Project::STATUTS)],
            'avancement_pourcentage' => ['required', 'integer', 'between:0,100'],
            'zone_id' => ['required', 'integer', Rule::exists('zones', 'id')],
            'infrastructure_id' => ['nullable', 'integer', Rule::exists('infrastructures', 'id')],
            'responsable_id' => ['required', 'integer', $this->backOfficeUserRule()],
        ];
    }

    protected function backOfficeUserRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')->where(
            fn ($query) => $query->whereIn('role', [
                UserRole::Gestionnaire->value,
                UserRole::Admin->value,
            ])
        );
    }
}
