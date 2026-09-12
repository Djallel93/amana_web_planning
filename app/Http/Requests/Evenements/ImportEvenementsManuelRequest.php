<?php
// app/Http/Requests/Evenements/ImportEvenementsManuelRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Evenements;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la saisie manuelle de plusieurs événements (formulaire dynamique
 * BulkEvenementImport.vue, section "Saisie manuelle" de evenements/import).
 *
 * Comportement "tout ou rien" identique à l'import CSV (EvenementCsvImporter
 * ::validate()) : si une seule ligne échoue, TOUTE la requête est rejetée —
 * Laravel redirige automatiquement vers la page précédente avec l'ensemble
 * des erreurs et les valeurs soumises (old('rows')), sans qu'aucun
 * événement n'ait été créé (le contrôleur ne s'exécute qu'après validation
 * complète).
 */
class ImportEvenementsManuelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'rows'                   => ['required', 'array', 'min:1'],
            'rows.*.nom'             => ['required', 'string', 'max:150'],
            'rows.*.date_debut'      => ['required', 'date'],
            'rows.*.date_fin'        => ['required', 'date'],
            'rows.*.description'    => ['nullable', 'string'],
            'rows.*.couleur'         => ['nullable', 'string', 'in:1,2,3,4,5,6,7,8,9,10,11'],
            'rows.*.calendar_ids'    => ['nullable', 'array'],
            'rows.*.calendar_ids.*'  => ['string', 'max:200'],
            'rows.*.taches'          => ['nullable', 'array'],
            'rows.*.taches.*'        => ['integer', 'exists:ref_taches,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required'          => 'Ajoutez au moins un événement.',
            'rows.min'               => 'Ajoutez au moins un événement.',
            'rows.*.nom.required'    => 'Le nom est obligatoire.',
            'rows.*.date_debut.required' => 'La date de début est obligatoire.',
            'rows.*.date_fin.required'   => 'La date de fin est obligatoire.',
            'rows.*.couleur.in'      => 'Couleur invalide.',
            'rows.*.taches.*.exists' => 'Tâche inconnue.',
        ];
    }

    /**
     * Contrainte "date_fin >= date_debut" par ligne, gérée ici plutôt
     * qu'avec after_or_equal:rows.*.date_debut — cette règle ne référence
     * pas de façon fiable le frère de même index au sein d'un tableau
     * imbriqué (contrairement à StoreEvenementRequest où date_debut/date_fin
     * sont deux champs plats, pas répétés).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('rows', []) as $index => $row) {
                $debut = $row['date_debut'] ?? null;
                $fin = $row['date_fin'] ?? null;

                if ($debut && $fin && $fin < $debut) {
                    $validator->errors()->add(
                        "rows.{$index}.date_fin",
                        'La date de fin doit être après ou égale à la date de début.'
                    );
                }
            }
        });
    }
}
