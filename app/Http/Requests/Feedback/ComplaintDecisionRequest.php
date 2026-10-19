<?php

namespace App\Http\Requests\Feedback;

use App\Models\Incident\IncidentFeedback;
use App\Services\ComplaintWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ComplaintDecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', Rule::in(array_keys(IncidentFeedback::COMPLAINT_DECISIONS))],
            'justification' => ['required', 'string', 'max:'.ComplaintWorkflowService::MAX_JUSTIFICATION_LENGTH],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $justification = trim((string) $this->input('justification'));

                if (mb_strlen($justification) < ComplaintWorkflowService::MIN_JUSTIFICATION_LENGTH) {
                    $validator->errors()->add(
                        'justification',
                        'La justification doit contenir au moins 10 caractères (espaces exclus).',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être « acceptée » ou « refusée ».',
            'justification.required' => 'Une justification est obligatoire pour chaque décision.',
            'justification.max' => 'La justification ne peut pas dépasser 2000 caractères.',
        ];
    }
}
