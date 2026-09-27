<?php
// app/Http/Requests/Personnes/StorePersonneRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Personnes;

use Amana\Shared\Support\PhoneFr;
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
            // ref_personnes vit dans amana_commun — voir UpdatePersonneRequest.
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:' . config('amana-shared.connection', 'commun') . '.ref_personnes,email'],
            // Format désormais partagé avec « Mon profil » et l'intake
            // familles/candidature planning — voir Amana\Shared\Support\PhoneFr.
            'telephone' => ['nullable', 'string', 'max:20', 'regex:' . PhoneFr::REGEX],
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
            'telephone.regex' => PhoneFr::MESSAGE,
            'statut.in' => 'Statut invalide.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Rôle invalide.',
        ];
    }
}