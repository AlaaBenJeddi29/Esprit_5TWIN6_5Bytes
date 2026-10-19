<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use App\Models\Incident\IncidentSimilarity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIncidentSimilarityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident instanceof Incident
            && ($this->user()?->can('manageSimilarities', $incident) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(array_keys(IncidentSimilarity::DECISIONS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision demandée est invalide.',
            'note.max' => 'La note ne peut pas dépasser 2000 caractères.',
        ];
    }
}
