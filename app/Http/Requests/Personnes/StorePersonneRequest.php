<?php
// app/Http/Requests/Personnes/StorePersonneRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Personnes;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:ref_personnes,email'],
            'telephone' => ['nullable', 'string', 'max:20', 'regex:/^(\+33|0033|0)[1-9](\s?[0-9]{2}){4}$/'],
            'date_debut_planning' => ['nullable', 'date'],
            'statut' => ['required', 'in:En attente,Validé,Suspendu,Archivé'],
            'role' => ['required', 'string', 'in:admin,gestionnaire,membre,benevole'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.email' => 'Format d\'email invalide.',
            'telephone.regex' => 'Format invalide. Exemples : 06 12 34 56 78, +33 6 12 34 56 78',
            'statut.in' => 'Statut invalide.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Rôle invalide.',
        ];
    }
}