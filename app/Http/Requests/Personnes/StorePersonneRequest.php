<?php
// app/Http/Requests/Personnes/StorePersonneRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation pour la création d'une personne.
 * Laravel appelle automatiquement cette classe avant d'entrer dans le contrôleur.
 */
class StorePersonneRequest extends FormRequest
{
    /** Seuls les utilisateurs authentifiés peuvent soumettre ce formulaire. */
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nom'                        => ['required', 'string', 'max:100'],
            'prenom'                     => ['required', 'string', 'max:100'],
            'email'                      => ['required', 'email', 'max:255', 'unique:ref_personnes,email'],
            'telephone'                  => ['nullable', 'string', 'max:20'],
            'date_debut_planning'        => ['nullable', 'date'],
            'date_inscription_benevole'  => ['nullable', 'date'],
            'statut'                     => ['required', 'in:En attente,Validé,Suspendu,Archivé'],
            'tirelire'                   => ['nullable', 'boolean'],
            'id_vehicule'                => ['nullable', 'integer', 'exists:ref_vehicules,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'       => 'Le nom est obligatoire.',
            'prenom.required'    => 'Le prénom est obligatoire.',
            'email.required'     => 'L\'adresse email est obligatoire.',
            'email.unique'       => 'Cette adresse email est déjà utilisée.',
            'email.email'        => 'Format d\'email invalide.',
            'statut.in'          => 'Statut invalide.',
            'id_vehicule.exists' => 'Ce véhicule n\'existe pas.',
        ];
    }
}
