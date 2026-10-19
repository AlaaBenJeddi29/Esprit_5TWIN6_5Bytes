<?php

namespace App\Http\Requests\Feedback;

use App\Models\Incident\IncidentFeedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintFilterRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_keys(IncidentFeedback::COMPLAINT_STATUSES))],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'reopen' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Le statut de réclamation sélectionné est invalide.',
            'rating.between' => 'La note doit être comprise entre 1 et 5.',
            'rating.integer' => 'La note doit être un entier.',
            'zone_id.exists' => 'La zone sélectionnée est invalide.',
            'from.date' => 'La date de début est invalide.',
            'to.date' => 'La date de fin est invalide.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
