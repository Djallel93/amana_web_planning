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
            'nom'                  => ['required', 'string', 'max:150'],
            'date_debut'           => ['required', 'date'],
            'date_fin'             => ['required', 'date', 'after_or_equal:date_debut'],
            'bloque_planning'      => ['nullable', 'boolean'],
            'necessite_benevoles'  => ['nullable', 'boolean'],
            'description'          => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
