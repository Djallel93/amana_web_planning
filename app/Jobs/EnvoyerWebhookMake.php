<?php
// app/Jobs/EnvoyerWebhookMake.php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job asynchrone : envoie le payload webhook vers Make.com.
 *
 * Le verbe HTTP ($method) reflète la nature de l'action :
 *   - post   : création (génération complète, créneau créé manuellement, nouvel événement)
 *   - patch  : modification (réassignation d'une tâche, événement modifié)
 *   - delete : suppression (désassignation d'une tâche, créneau supprimé, événement supprimé)
 *
 * Deux scénarios Make.com distincts, chacun avec sa propre URL de webhook,
 * sélectionnée via $cible :
 *   - 'planning'  → services.make.webhook_url             (créneaux du planning)
 *   - 'evenement' → services.make.webhook_url_evenements  (événements organisationnels)
 *
 * Chaque appel inclut le header `x-make-apikey` (config services.make.api_key,
 * partagée par les deux scénarios) en plus de l'URL. Les deux (URL + clé)
 * doivent être renseignées pour la cible concernée, sinon l'envoi est ignoré
 * (avec un log d'avertissement).
 */
class EnvoyerWebhookMake implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nombre de tentatives en cas d'échec */
    public int $tries = 3;

    /** Délai entre les tentatives (secondes) */
    public int $backoff = 60;

    private const METHODES_AUTORISEES = ['post', 'patch', 'delete'];
    private const CIBLES_AUTORISEES = ['planning', 'evenement'];

    public function __construct(
        private readonly array $payload,
        private readonly string $method = 'post',
        private readonly string $cible = 'planning',
    ) {
    }

    /**
     * Exécution du job : envoi HTTP vers Make.com avec le verbe approprié,
     * vers l'URL du scénario correspondant à $cible.
     */
    public function handle(): void
    {
        $cible = in_array($this->cible, self::CIBLES_AUTORISEES, true) ? $this->cible : 'planning';
        $configKey = $cible === 'evenement' ? 'webhook_url_evenements' : 'webhook_url';

        // config() fonctionne correctement après php artisan config:cache,
        // contrairement à env() qui retourne null en production.
        $url = config("services.make.{$configKey}");
        $apiKey = config('services.make.api_key');
        $methode = in_array($this->method, self::METHODES_AUTORISEES, true) ? $this->method : 'post';

        if (empty($url)) {
            Log::warning("[WebhookMake] services.make.{$configKey} non configurée — envoi ignoré.", ['cible' => $cible]);
            return;
        }

        if (empty($apiKey)) {
            Log::warning('[WebhookMake] services.make.api_key non configurée — envoi ignoré.', ['cible' => $cible]);
            return;
        }

        Log::info('[WebhookMake] Envoi du webhook vers Make.com', [
            'url' => $url,
            'method' => strtoupper($methode),
            'cible' => $cible,
            'nb_creneaux' => count($this->payload['creneaux'] ?? []),
        ]);

        $request = Http::timeout(30)->withHeaders([
            'Content-Type' => 'application/json',
            'x-make-apikey' => $apiKey,
        ]);

        $response = match ($methode) {
            'patch' => $request->patch($url, $this->payload),
            'delete' => $request->delete($url, $this->payload),
            default => $request->post($url, $this->payload),
        };

        if ($response->successful()) {
            Log::info('[WebhookMake] Webhook envoyé avec succès.', [
                'status' => $response->status(),
                'method' => strtoupper($methode),
                'cible' => $cible,
            ]);

            audit('webhook', 'planning', null, null, [
                'url' => $url,
                'method' => strtoupper($methode),
                'status' => $response->status(),
                'cible' => $cible,
                'nb_creneaux' => count($this->payload['creneaux'] ?? []),
            ]);
        } else {
            Log::error('[WebhookMake] Échec de l\'envoi du webhook.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'method' => strtoupper($methode),
                'cible' => $cible,
            ]);

            // Déclenche une nouvelle tentative automatique
            $this->fail(new \RuntimeException(
                "Webhook Make.com échoué : HTTP {$response->status()} ({$methode}, cible={$cible})"
            ));
        }
    }
}
