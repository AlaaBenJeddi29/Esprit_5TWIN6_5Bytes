<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\BackOfficeRequest;
use Illuminate\Validation\Rule;

class UpdateContractorRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'specialite' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('contractors', 'email')->ignore($this->route('contractor')),
            ],
            'telephone' => ['required', 'string', 'max:20'],
            'adresse' => ['required', 'string'],
        ];
    }
}
