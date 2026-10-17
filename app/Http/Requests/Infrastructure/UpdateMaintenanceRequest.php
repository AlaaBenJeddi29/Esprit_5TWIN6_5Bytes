<?php

namespace App\Http\Requests\Infrastructure;

use App\Http\Requests\BackOfficeRequest;

class UpdateMaintenanceRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'infrastructure_id' => ['required', 'integer', 'exists:infrastructures,id'],
            'technicien_id' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date_intervention' => ['required', 'date'],
            'cout' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['required', 'string', 'max:255'],
        ];
    }
}
