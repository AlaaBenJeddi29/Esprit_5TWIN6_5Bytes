<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentAssistQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCitoyen();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'type' => ['nullable', 'string', Rule::in(array_keys(Incident::TYPES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.max' => 'Le titre ne peut pas dépasser 180 caractères.',
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'description.max' => 'La description ne peut pas dépasser 2 000 caractères.',
            'type.in' => "Le type d'incident sélectionné est invalide.",
        ];
    }
}
