<?php
// app/Http/Requests/Personnes/UpdatePersonneRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:255', "unique:ref_personnes,email,{$id}"],
            'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\+33|0033|0)[1-9](\s?[0-9]{2}){4}$/'],
            'date_debut_planning' => ['nullable', 'date'],
            'date_inscription_benevole' => ['nullable', 'date'],
            'statut' => ['required', 'in:En attente,Validé,Suspendu,Archivé'],
            'role' => ['required', 'string', 'in:admin,gestionnaire,membre,benevole'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse email est déjà utilisée par une autre personne.',
            'telephone.regex' => 'Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Rôle invalide.',
        ];
    }
}