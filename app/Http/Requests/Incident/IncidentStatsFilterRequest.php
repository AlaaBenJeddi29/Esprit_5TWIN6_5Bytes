<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentStatsFilterRequest extends FormRequest
{
    public const PERIODS = [
        '30d' => '30 derniers jours',
        '90d' => '90 derniers jours',
        'year' => 'Année en cours',
        'custom' => 'Période personnalisée',
    ];

    public function authorize(): bool
    {
        return $this->user()?->canAccessBackOffice() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCustom = $this->input('period') === 'custom';

        return [
            'period' => ['nullable', 'string', Rule::in(array_keys(self::PERIODS))],
            'from' => [$isCustom ? 'required' : 'nullable', 'date'],
            'to' => array_values(array_filter([
                $isCustom ? 'required' : 'nullable',
                'date',
                $this->filled('from') ? 'after_or_equal:from' : null,
            ])),
        ];
    }

    public function periodKey(): string
    {
        return $this->validated('period') ?? '30d';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => 'La période sélectionnée est invalide.',
            'from.required' => 'La date de début est obligatoire pour une période personnalisée.',
            'from.date' => 'La date de début est invalide.',
            'to.required' => 'La date de fin est obligatoire pour une période personnalisée.',
            'to.date' => 'La date de fin est invalide.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
