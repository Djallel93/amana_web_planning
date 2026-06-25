<?php
// app/Http/Controllers/CalendriersController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy vers Make.com pour récupérer la liste des calendriers Google Calendar.
 *
 * Un GET sur le webhook Make.com retourne un JSON de la forme :
 *   { "calendars": ["Calendrier A", "Calendrier B", ...] }
 *
 * Cette route est appelée en AJAX depuis les formulaires qui ont un champ
 * de sélection de calendrier (événements et paramètres).
 *
 * Route : GET /api/calendriers (middleware auth)
 */
class CalendriersController extends Controller
{
    /**
     * Récupère la liste des calendriers depuis Make.com et la retourne en JSON.
     *
     * En cas d'échec (webhook non configuré, timeout, erreur réseau),
     * retourne un tableau vide avec un message d'erreur — le formulaire
     * bascule alors sur un input texte libre en fallback.
     */
    public function index(): JsonResponse
    {
        $url = config('services.make.webhook_url');
        $apiKey = config('services.make.api_key');

        if (empty($url)) {
            Log::warning('[CalendriersController] MAKE_WEBHOOK_URL non configurée.');
            return response()->json([
                'calendars' => [],
                'erreur' => 'Webhook Make.com non configuré.',
            ]);
        }

        try {
            Log::info('[CalendriersController] GET calendriers Make.com', ['url' => $url]);

            $request = Http::timeout(10);

            if (!empty($apiKey)) {
                $request = $request->withHeaders(['x-make-apikey' => $apiKey]);
            }

            $response = $request->get($url);

            if (!$response->successful()) {
                Log::warning('[CalendriersController] Réponse non-2xx Make.com', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return response()->json([
                    'calendars' => [],
                    'erreur' => 'Réponse inattendue du webhook (HTTP ' . $response->status() . ').',
                ]);
            }

            $data = $response->json();

            if (!isset($data['calendars']) || !is_array($data['calendars'])) {
                Log::warning('[CalendriersController] Format de réponse inattendu', [
                    'data' => $data,
                ]);
                return response()->json([
                    'calendars' => [],
                    'erreur' => 'Format de réponse Make.com inattendu.',
                ]);
            }

            $calendars = array_values(array_filter($data['calendars'], 'is_string'));
            sort($calendars);

            Log::info('[CalendriersController] Calendriers récupérés', [
                'count' => count($calendars),
            ]);

            return response()->json(['calendars' => $calendars]);

        } catch (\Throwable $e) {
            Log::error('[CalendriersController] Erreur lors du GET Make.com', [
                'erreur' => $e->getMessage(),
                'classe' => get_class($e),
            ]);

            return response()->json([
                'calendars' => [],
                'erreur' => 'Impossible de contacter Make.com : ' . $e->getMessage(),
            ]);
        }
    }
}