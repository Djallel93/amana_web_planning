<?php
// app/Http/Requests/Evenements/ImportEvenementsRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Evenements;

use Illuminate\Foundation\Http\FormRequest;

class ImportEvenementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv.required' => 'Veuillez sélectionner un fichier CSV.',
            'csv.file'     => 'Le fichier envoyé est invalide.',
            'csv.mimes'    => 'Le fichier doit être au format CSV.',
            'csv.max'      => 'Le fichier ne doit pas dépasser 2 Mo.',
        ];
    }
}
