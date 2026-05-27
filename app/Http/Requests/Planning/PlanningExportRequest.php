<?php
// app/Http/Requests/Planning/PlanningExportRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

/** Validation du formulaire d'export PDF du planning. */
class PlanningExportRequest extends FormRequest
{
    public function authorize(): boolF
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after_or_equal:date_debut'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_debut.required'     => 'La date de début est obligatoire.',
            'date_fin.required'       => 'La date de fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
