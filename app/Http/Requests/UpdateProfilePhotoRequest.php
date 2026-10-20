<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => 'photo de profil',
        ];
    }
}
