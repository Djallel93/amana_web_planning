<?php
// app/Http/Requests/Bilan/StoreBilanAmanaFoodRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Bilan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour l'enregistrement (upsert) du groupe Amana food d'un
 * bilan quotidien — indépendant du groupe Présences (voir
 * StoreBilanPresenceRequest), afin que deux personnes puissent éditer
 * chaque groupe séparément sans s'écraser mutuellement.
 */
class StoreBilanAmanaFoodRequest extends FormRequest
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
            'date'           => ['required', 'date'],
            'montant_carte'  => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'montant_espece' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant_carte.min'  => 'Le montant carte bancaire ne peut pas être négatif.',
            'montant_espece.min' => 'Le montant espèces ne peut pas être négatif.',
        ];
    }
}
