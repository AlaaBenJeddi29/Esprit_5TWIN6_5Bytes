<?php

namespace App\Http\Requests;

use App\Models\AlertSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCitoyen();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'type_alerte' => ['required', Rule::in(array_keys(AlertSubscription::TYPES))],
        ];
    }
}
