<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base commune aux requêtes du Back Office : seul un gestionnaire ou un
 * administrateur (rôle lu côté serveur) est autorisé. Le middleware `role`
 * constitue la première barrière ; cette autorisation est une défense en
 * profondeur au niveau de la requête.
 */
abstract class BackOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccessBackOffice();
    }
}
