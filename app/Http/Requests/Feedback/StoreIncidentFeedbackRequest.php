<?php

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIncidentFeedbackRequest extends FormRequest
{
    public const MIN_COMPLAINT_LENGTH = 10;

    public const MAX_COMMENT_LENGTH = 2000;

    public const MAX_COMPLAINT_LENGTH = 2000;

    public function authorize(): bool
    {
        return $this->user()?->isCitoyen() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.self::MAX_COMMENT_LENGTH],
            'resolution_confirmed' => ['required', 'boolean'],
            'complaint_description' => ['nullable', 'string', 'max:'.self::MAX_COMPLAINT_LENGTH],
            'reopen_requested' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cohérence métier entre résolution confirmée, réclamation et demande
     * de réouverture.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $confirmed = $this->boolean('resolution_confirmed');
                $reopen = $this->boolean('reopen_requested');
                $description = trim((string) $this->input('complaint_description'));

                if ($description !== '' && mb_strlen($description) < self::MIN_COMPLAINT_LENGTH) {
                    $validator->errors()->add(
                        'complaint_description',
                        'La description de la réclamation doit contenir au moins 10 caractères (espaces exclus).',
                    );
                }

                if ($confirmed && $description !== '') {
                    $validator->errors()->add(
                        'complaint_description',
                        'Une réclamation ne peut pas être déposée lorsque vous confirmez la résolution du problème.',
                    );
                }

                if (! $confirmed && $description === '') {
                    $validator->errors()->add(
                        'complaint_description',
                        'Décrivez le problème persistant pour déposer une réclamation (10 caractères minimum).',
                    );
                }

                if ($reopen && $confirmed) {
                    $validator->errors()->add(
                        'reopen_requested',
                        'Une réouverture ne peut pas être demandée lorsque vous confirmez la résolution du problème.',
                    );
                }

                if ($reopen && $description === '') {
                    $validator->errors()->add(
                        'complaint_description',
                        'Une demande de réouverture nécessite une description de la réclamation.',
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
            'rating.required' => 'La note de satisfaction est obligatoire.',
            'rating.integer' => 'La note de satisfaction doit être un entier.',
            'rating.between' => 'La note de satisfaction doit être comprise entre 1 et 5.',
            'comment.max' => 'Le commentaire ne peut pas dépasser 2000 caractères.',
            'resolution_confirmed.required' => 'Indiquez si le problème est réellement résolu.',
            'resolution_confirmed.boolean' => 'La confirmation de résolution est invalide.',
            'complaint_description.max' => 'La description de la réclamation ne peut pas dépasser 2000 caractères.',
            'reopen_requested.boolean' => 'La demande de réouverture est invalide.',
        ];
    }
}
