<?php
// app/Http/Requests/Bilan/StoreBilanRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Bilan;

use Illuminate\Foundation\Http\FormRequest;

/** Validation pour l'enregistrement (upsert) d'un bilan quotidien. */
class StoreBilanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tout utilisateur connecté peut enregistrer un bilan — enregistrement
        // unique et partagé par date, pas de notion de propriétaire.
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'date'            => ['required', 'date'],
            'montant_carte'   => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'montant_espece'  => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'nb_presents'     => ['required', 'integer', 'min:0', 'max:65535'],
            'nb_en_ligne'     => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant_carte.min'  => 'Le montant carte bancaire ne peut pas être négatif.',
            'montant_espece.min' => 'Le montant espèces ne peut pas être négatif.',
            'nb_presents.min'    => 'Le nombre de présents ne peut pas être négatif.',
            'nb_en_ligne.min'    => 'Le nombre de personnes en ligne ne peut pas être négatif.',
        ];
    }
}
