<?php

namespace App\Http\Requests\Quality;

use App\Enums\UserRole;
use App\Http\Requests\BackOfficeRequest;
use Illuminate\Validation\Rule;

class UpdateQualityZoneAssignmentsRequest extends BackOfficeRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gestionnaire_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Gestionnaire->value),
            ],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'distinct', 'exists:zones,id'],
        ];
    }
}
