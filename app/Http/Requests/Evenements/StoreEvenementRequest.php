<?php
// app/Http/Requests/Evenements/StoreEvenementRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Evenements;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvenementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nom'           => ['required', 'string', 'max:150'],
            'date_debut'    => ['required', 'date'],
            'date_fin'      => ['required', 'date', 'after_or_equal:date_debut'],
            'description'    => ['nullable', 'string'],
            'calendar_ids'   => ['nullable', 'array'],
            'calendar_ids.*' => ['string', 'max:200', 'distinct'],
            'taches'         => ['nullable', 'array'],
            'taches.*'       => ['integer', 'exists:ref_taches,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'calendar_ids.*.max'      => 'L\'identifiant du calendrier ne doit pas dépasser 200 caractères.',
            'calendar_ids.*.distinct' => 'Ce calendrier est déjà sélectionné.',
        ];
    }
}
