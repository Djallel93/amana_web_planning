<?php
// app/Http/Requests/Bilan/StoreBilanPresenceRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Bilan;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour l'enregistrement (upsert) du groupe Présences d'un
 * bilan quotidien — indépendant du groupe Amana food (voir
 * StoreBilanAmanaFoodRequest), afin que deux personnes puissent éditer
 * chaque groupe séparément sans s'écraser mutuellement.
 *
 * ── Tout ou rien ───────────────────────────────────────────────────────
 * Voir StoreBilanAmanaFoodRequest::withValidator() pour le détail : les
 * deux effectifs sont soit tous les deux renseignés, soit tous les deux
 * null ("pas de cours"), jamais un mélange.
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
            'nb_presents' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'nb_en_ligne' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'nb_presents.min' => 'Le nombre de présents ne peut pas être négatif.',
            'nb_en_ligne.min' => 'Le nombre de personnes en ligne ne peut pas être négatif.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $champs       = ['nb_presents', 'nb_en_ligne'];
            $valeurs      = array_map(fn (string $champ) => $this->input($champ), $champs);
            $nbRenseignes = count(array_filter($valeurs, fn ($v) => $v !== null));

            if ($nbRenseignes > 0 && $nbRenseignes < count($champs)) {
                $validator->errors()->add(
                    'nb_presents',
                    'Renseignez les deux effectifs, ou laissez-les tous vides pour marquer "pas de cours".'
                );
            }
        });
    }
}
