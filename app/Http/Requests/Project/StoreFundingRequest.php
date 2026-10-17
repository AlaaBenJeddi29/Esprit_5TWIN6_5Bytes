<?php

namespace App\Http\Requests\Project;

use App\Enums\UserRole;
use App\Http\Requests\BackOfficeRequest;
use App\Models\Project\Funding;
use Illuminate\Validation\Rule;

class StoreFundingRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(Funding::SOURCES)],
            'donateur_id' => [
                'nullable', 'integer',
                'prohibited_unless:source,don',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', UserRole::Citoyen->value)
                ),
            ],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_versement' => ['required', 'date'],
        ];
    }
}
