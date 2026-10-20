<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentAssistComposeRequest extends FormRequest
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
            'answers' => ['required', 'array', 'max:3', 'array:q1,q2,q3'],
            'answers.*' => ['required', 'string', 'min:1', 'max:300'],
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
            'answers.required' => 'Répondez à au moins une question.',
            'answers.max' => 'Vous pouvez fournir au plus 3 réponses.',
            'answers.array' => 'Le format des réponses est invalide.',
            'answers.*.max' => 'Chaque réponse ne peut pas dépasser 300 caractères.',
        ];
    }
}
