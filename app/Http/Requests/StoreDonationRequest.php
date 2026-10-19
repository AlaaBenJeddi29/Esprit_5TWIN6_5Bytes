<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
{
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
            'montant' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:'.config('donations.min_amount'),
                'max:'.config('donations.max_amount'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'montant.min' => 'Le montant minimum est de '.number_format((float) config('donations.min_amount'), 2, ',', ' ').' €.',
            'montant.max' => 'Le montant maximum est de '.number_format((float) config('donations.max_amount'), 2, ',', ' ').' €.',
            'montant.decimal' => 'Le montant ne peut pas comporter plus de deux décimales.',
        ];
    }
}
