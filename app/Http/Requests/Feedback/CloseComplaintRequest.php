<?php

namespace App\Http\Requests\Feedback;

use App\Services\ComplaintWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CloseComplaintRequest extends FormRequest
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
            'justification' => ['nullable', 'string', 'max:'.ComplaintWorkflowService::MAX_JUSTIFICATION_LENGTH],
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

                if ($justification !== ''
                    && mb_strlen($justification) < ComplaintWorkflowService::MIN_JUSTIFICATION_LENGTH) {
                    $validator->errors()->add(
                        'justification',
                        'La justification de clôture doit contenir au moins 10 caractères (espaces exclus).',
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
            'justification.max' => 'La justification de clôture ne peut pas dépasser 2000 caractères.',
        ];
    }
}
