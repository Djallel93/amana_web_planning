<?php
// app/Http/Requests/Absences/UpdateAbsenceRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Absences;

use Illuminate\Foundation\Http\FormRequest;

/** Validation pour la modification d'une absence. */
class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'id_personne' => ['required', 'integer', 'exists:ref_personnes,id'],
            'date_debut'  => ['required', 'date'],
            'date_fin'    => ['required', 'date', 'after_or_equal:date_debut'],
            'raison'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_personne.exists'      => 'Cette personne n\'existe pas.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
