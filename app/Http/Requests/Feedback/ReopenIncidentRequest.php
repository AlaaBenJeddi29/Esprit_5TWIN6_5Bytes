<?php

namespace App\Http\Requests\Feedback;

use App\Services\ComplaintWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReopenIncidentRequest extends FormRequest
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
                        'Le motif de réouverture doit contenir au moins 10 caractères (espaces exclus).',
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
            'justification.required' => 'Un motif de réouverture est obligatoire.',
            'justification.max' => 'Le motif de réouverture ne peut pas dépasser 2000 caractères.',
        ];
    }
}
