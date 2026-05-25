<?php
// app/Http/Requests/Evenements/UpdateEvenementRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Evenements;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEvenementRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

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
}
