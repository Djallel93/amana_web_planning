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
            // Voir StoreAbsenceRequest — ref_personnes vit dans amana_commun,
            // pas dans la base par défaut de cette app.
            'id_personne' => ['required', 'integer', 'exists:' . config('amana-shared.connection', 'commun') . '.ref_personnes,id'],
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
