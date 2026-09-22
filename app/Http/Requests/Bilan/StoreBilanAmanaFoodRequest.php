<?php
// app/Http/Requests/Bilan/StoreBilanAmanaFoodRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Bilan;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour l'enregistrement (upsert) du groupe Amana food d'un
 * bilan quotidien — indépendant du groupe Présences (voir
 * StoreBilanPresenceRequest), afin que deux personnes puissent éditer
 * chaque groupe séparément sans s'écraser mutuellement.
 *
 * ── Tout ou rien ─────────────────────────────────────────────────────────
 * Les trois montants sont individuellement `nullable`, mais withValidator()
 * ci-dessous impose qu'ils soient soit TOUS renseignés, soit TOUS null —
 * jamais un mélange. Champs tous null = "pas de cours ce jour-là" (même
 * état que BilanController::resetAmanaFood, mais accessible ici à
 * n'importe quel utilisateur connecté via le bouton Enregistrer normal, ex.
 * pour annuler une saisie faite par erreur sur la mauvaise date — sans les
 * exiger gestionnaire/admin ni la confirmation du bouton Réinitialiser
 * dédié). Voir aussi le garde-fou côté client dans BilanView.vue
 * (champsIncomplets), qui bloque déjà le cas partiel avant l'envoi — cette
 * règle serveur reste la source de vérité si jamais ce garde-fou est
 * contourné (autre client, requête directe...).
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
            'date'            => ['required', 'date'],
            'montant_carte'   => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'montant_espece'  => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            // Nourriture offerte (aucune charge réelle) => 0, pas null : NULL reste
            // réservé à "pas de cours ce jour-là" (voir Bilan et BilanController).
            'montant_charges' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant_carte.min'   => 'Le montant carte bancaire ne peut pas être négatif.',
            'montant_espece.min'  => 'Le montant espèces ne peut pas être négatif.',
            'montant_charges.min' => 'Le montant des charges ne peut pas être négatif.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $champs   = ['montant_carte', 'montant_espece', 'montant_charges'];
            $valeurs  = array_map(fn (string $champ) => $this->input($champ), $champs);
            $nbRenseignes = count(array_filter($valeurs, fn ($v) => $v !== null));

            if ($nbRenseignes > 0 && $nbRenseignes < count($champs)) {
                $validator->errors()->add(
                    'montant_carte',
                    'Renseignez les trois montants, ou laissez-les tous vides pour marquer "pas de cours".'
                );
            }
        });
    }
}
