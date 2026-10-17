<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\BackOfficeRequest;
use Illuminate\Validation\Rule;

class StoreProjectPhaseRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:150'],
            'contractor_id' => ['required', 'integer', Rule::exists('contractors', 'id')],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'cout' => ['required', 'numeric', 'min:0'],
            'avancement' => ['required', 'integer', 'between:0,100'],
        ];
    }
}
