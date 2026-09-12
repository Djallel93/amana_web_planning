<?php
// app/Http/Requests/Bilan/StoreBilanPresenceRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Bilan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour l'enregistrement (upsert) du groupe Présences d'un
 * bilan quotidien — indépendant du groupe Amana food (voir
 * StoreBilanAmanaFoodRequest), afin que deux personnes puissent éditer
 * chaque groupe séparément sans s'écraser mutuellement.
 */
class StoreBilanPresenceRequest extends FormRequest
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
            'date'        => ['required', 'date'],
            'nb_presents' => ['required', 'integer', 'min:0', 'max:65535'],
            'nb_en_ligne' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'nb_presents.min' => 'Le nombre de présents ne peut pas être négatif.',
            'nb_en_ligne.min' => 'Le nombre de personnes en ligne ne peut pas être négatif.',
        ];
    }
}
