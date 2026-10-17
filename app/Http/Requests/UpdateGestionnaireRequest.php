<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UpdateGestionnaireRequest extends FormRequest
{
    /**
     * Seul un administrateur peut modifier un compte gestionnaire.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('gestionnaire')),
            ],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
