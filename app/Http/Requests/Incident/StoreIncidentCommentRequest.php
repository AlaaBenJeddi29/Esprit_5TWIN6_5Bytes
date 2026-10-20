<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // Requête partagée Front (citoyen propriétaire) / Back (gestionnaire).
        return $user !== null && ($user->isCitoyen() || $user->canAccessBackOffice());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
