<?php
// app/Http/Requests/Absences/UpdateAbsenceRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Absences;

use App\Models\Absence;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Refuse que la modification rende cette absence strictement identique à une AUTRE
     * (même personne, mêmes dates) — l'absence modifiée elle-même est exclue, donc
     * ré-enregistrer sans changement reste permis. Exécuté seulement si les règles
     * ci-dessus passent (les dates sont alors exploitables).
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
            )->where('id', '!=', (int) $this->route('id'))->exists();

            if ($doublon) {
                $validator->errors()->add('date_debut', Absence::MESSAGE_DOUBLON);
            }
        }];
    }

    public function messages(): array
    {
        return [
            'id_personne.exists'      => 'Cette personne n\'existe pas.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
