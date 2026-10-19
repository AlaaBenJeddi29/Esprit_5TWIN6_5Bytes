<?php

namespace App\Http\Requests\Incident;

use App\Models\Incident\Incident;
use App\Services\IncidentWorkflowService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentStatusRequest extends FormRequest
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
        $incident = $this->route('incident');
        $target = (string) $this->input('status');
        $workflow = app(IncidentWorkflowService::class);

        $isReopening = $incident instanceof Incident
            && $workflow->isReopening($incident->status, $target);

        return [
            'status' => [
                'required',
                Rule::in(array_keys(Incident::STATUSES)),
                function (string $attribute, mixed $value, Closure $fail) use ($incident, $workflow): void {
                    if ($incident instanceof Incident && is_string($value)
                        && ! $workflow->canTransition($incident->status, $value)) {
                        $fail(sprintf(
                            'Transition non autorisée depuis le statut « %s ».',
                            $incident->statusLabel(),
                        ));
                    }
                },
            ],
            'comment' => [
                'nullable',
                'string',
                'max:'.IncidentWorkflowService::MAX_NOTE_LENGTH,
                Rule::requiredIf(fn () => $isReopening),
                function (string $attribute, mixed $value, Closure $fail) use ($isReopening): void {
                    if ($isReopening && is_string($value)
                        && mb_strlen(trim($value)) < IncidentWorkflowService::MIN_NOTE_LENGTH) {
                        $fail('Le motif de réouverture doit contenir au moins 10 caractères (espaces exclus).');
                    }
                },
            ],
            'resolution_note' => [
                'nullable',
                'string',
                'max:'.IncidentWorkflowService::MAX_NOTE_LENGTH,
                Rule::requiredIf(fn () => $target === 'traite'),
                function (string $attribute, mixed $value, Closure $fail) use ($target): void {
                    if ($target === 'traite' && is_string($value)
                        && mb_strlen(trim($value)) < IncidentWorkflowService::MIN_NOTE_LENGTH) {
                        $fail('Le compte rendu de résolution doit contenir au moins 10 caractères (espaces exclus).');
                    }
                },
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'max:'.IncidentWorkflowService::MAX_NOTE_LENGTH,
                Rule::requiredIf(fn () => $target === 'rejete'),
                function (string $attribute, mixed $value, Closure $fail) use ($target): void {
                    if ($target === 'rejete' && is_string($value)
                        && mb_strlen(trim($value)) < IncidentWorkflowService::MIN_NOTE_LENGTH) {
                        $fail('Le motif de rejet doit contenir au moins 10 caractères (espaces exclus).');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le statut cible est obligatoire.',
            'status.in' => 'Le statut cible est invalide.',
            'comment.required' => 'Un motif de réouverture est obligatoire.',
            'comment.max' => 'Le commentaire ne peut pas dépasser 2000 caractères.',
            'resolution_note.required' => 'Un compte rendu de résolution est obligatoire pour clôturer l\'incident.',
            'resolution_note.max' => 'Le compte rendu de résolution ne peut pas dépasser 2000 caractères.',
            'rejection_reason.required' => 'Un motif de rejet est obligatoire.',
            'rejection_reason.max' => 'Le motif de rejet ne peut pas dépasser 2000 caractères.',
        ];
    }
}
