<?php
// app/Http/Controllers/BilanController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Bilan\StoreBilanRequest;
use App\Models\Bilan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Contrôleur pour le bilan quotidien (Amana food + Présences).
 *
 * Un seul enregistrement partagé par date — tout utilisateur connecté peut
 * consulter et modifier n'importe quelle date, au même titre.
 *
 * Routes :
 *   GET  /bilan             → shell Blade (point de montage BilanView.vue)
 *   GET  /bilan/data?date=  → JSON : bilan existant pour une date (ou vide)
 *   POST /bilan/data        → upsert du bilan pour une date
 */
class BilanController extends Controller
{
    public function index(): View
    {
        return view('bilan.index');
    }

    /**
     * Retourne le bilan enregistré pour une date donnée, ou des valeurs à
     * zéro si aucun bilan n'existe encore pour cette date.
     *
     * GET /bilan/data?date=YYYY-MM-DD
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date  = $request->query('date');
        $bilan = Bilan::with('personneMaj')->whereDate('date', $date)->first();

        return response()->json($this->serialize($date, $bilan));
    }

    /**
     * Enregistre (crée ou met à jour) le bilan d'une date.
     *
     * POST /bilan/data
     */
    public function store(StoreBilanRequest $request): JsonResponse
    {
        /** @var \App\Models\Personne $user */
        $user = Auth::user();

        $date  = $request->validated('date');
        $avant = Bilan::whereDate('date', $date)->first()?->toArray();

        $bilan = Bilan::updateOrCreate(
            ['date' => $date],
            [
                'montant_carte'   => $request->validated('montant_carte'),
                'montant_espece'  => $request->validated('montant_espece'),
                'nb_presents'     => $request->validated('nb_presents'),
                'nb_en_ligne'     => $request->validated('nb_en_ligne'),
                'id_personne_maj' => $user->id,
            ]
        );
        $bilan->load('personneMaj');

        audit($avant ? 'update' : 'create', 'bilan', $bilan->id, $avant, $bilan->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Bilan enregistré.',
            'bilan'   => $this->serialize($date, $bilan),
        ]);
    }

    /**
     * Sérialise un bilan (ou son absence) pour le format attendu par BilanView.vue.
     */
    private function serialize(string $date, ?Bilan $bilan): array
    {
        return [
            'date'           => $date,
            'montantCarte'   => $bilan ? (float) $bilan->montant_carte : 0.0,
            'montantEspece'  => $bilan ? (float) $bilan->montant_espece : 0.0,
            'nbPresents'     => $bilan?->nb_presents ?? 0,
            'nbEnLigne'      => $bilan?->nb_en_ligne ?? 0,
            'existe'         => $bilan !== null,
            'derniereMaj'    => $bilan?->updated_at?->locale('fr')->isoFormat('D MMM YYYY [à] HH:mm'),
            'derniereMajPar' => $bilan?->personneMaj
                ? $bilan->personneMaj->prenom . ' ' . $bilan->personneMaj->nom
                : null,
        ];
    }
}
