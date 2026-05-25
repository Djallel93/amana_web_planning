<?php
// app/Http/Requests/Personnes/UpdatePersonneRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;

/** Validation pour la mise à jour d'une personne. */
class UpdatePersonneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // Récupère l'ID depuis l'URL (/personnes/{id})
        $id = $this->route('id');

        return [
            'nom'                        => ['required', 'string', 'max:100'],
            'prenom'                     => ['required', 'string', 'max:100'],
            // Ignore l'email de la personne courante pour la règle unique
            'email'                      => ['required', 'email', 'max:255', "unique:ref_personnes,email,{$id}"],
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
            'email.unique' => 'Cette adresse email est déjà utilisée par une autre personne.',
        ];
    }
}
