<?php
// app/Http/Requests/Absences/StoreAbsenceRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Absences;

use App\Models\Absence;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Validation pour l'ajout d'une absence. */
class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // ref_personnes vit dans amana_commun (connexion 'commun'), pas
            // dans la base par défaut de cette app — sans le préfixe de
            // connexion, `exists` interroge la mauvaise base (celle-ci n'a
            // qu'une ref_personnes locale historique, vide) et rejette tout
            // le monde avec "Cette personne n'existe pas.".
            'id_personne' => ['required', 'integer', 'exists:' . config('amana-shared.connection', 'commun') . '.ref_personnes,id'],
            'date_debut'  => ['required', 'date'],
            'date_fin'    => ['required', 'date', 'after_or_equal:date_debut'],
            'raison'      => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Refuse une absence strictement identique à une existante (même personne, mêmes
     * dates) : c'est ce que produit une double soumission du formulaire. Exécuté
     * seulement si les règles ci-dessus passent (les dates sont alors exploitables).
     * Le contrôleur re-vérifie sous verrou pour le cas de deux requêtes simultanées.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $doublon = Absence::identique(
                (int) $this->input('id_personne'),
                Carbon::parse((string) $this->input('date_debut'))->toDateString(),
                Carbon::parse((string) $this->input('date_fin'))->toDateString(),
            )->exists();

            if ($doublon) {
                $validator->errors()->add('date_debut', Absence::MESSAGE_DOUBLON);
            }
        }];
    }

    public function messages(): array
    {
        return [
            'id_personne.exists'       => 'Cette personne n\'existe pas.',
            'date_fin.after_or_equal'  => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
