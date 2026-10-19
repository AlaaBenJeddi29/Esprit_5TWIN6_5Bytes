<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RespondComplaintRequest extends FormRequest
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
            'response' => ['required', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (mb_strlen(trim((string) $this->input('response'))) < 2) {
                    $validator->errors()->add(
                        'response',
                        'La réponse doit contenir au moins 2 caractères (espaces exclus).',
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
            'response.required' => 'La réponse au citoyen est obligatoire.',
            'response.min' => 'La réponse doit contenir au moins 2 caractères.',
            'response.max' => 'La réponse ne peut pas dépasser 2000 caractères.',
        ];
    }
}
