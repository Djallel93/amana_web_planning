<?php
// app/Http/Requests/Planning/PlanningGenerateRequest.php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

/** Validation du formulaire de génération du planning. */
class PlanningGenerateRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'date_debut' => ['required', 'date', 'after_or_equal:today'],
            'semaines'   => ['required', 'integer', 'min:1', 'max:52'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_debut.required'        => 'La date de début est obligatoire.',
            'date_debut.after_or_equal'  => 'La date de début doit être aujourd\'hui ou dans le futur.',
            'semaines.min'               => 'Minimum 1 semaine.',
            'semaines.max'               => 'Maximum 52 semaines.',
        ];
    }
}
