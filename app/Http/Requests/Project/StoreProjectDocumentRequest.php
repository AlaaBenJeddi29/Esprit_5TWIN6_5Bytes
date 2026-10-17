<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\BackOfficeRequest;
use App\Models\Project\ProjectDocument;
use Illuminate\Validation\Rule;

class StoreProjectDocumentRequest extends BackOfficeRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('document') !== null;

        return [
            'titre' => ['required', 'string', 'max:200'],
            'fichier' => [
                $isUpdate ? 'nullable' : 'required',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif',
            ],
            'type' => ['required', Rule::in(ProjectDocument::TYPES)],
            'est_public' => ['sometimes', 'boolean'],
        ];
    }
}
