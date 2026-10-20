<?php

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentPhotoRequest extends FormRequest
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
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:4096',
            ],
        ];
    }
}
