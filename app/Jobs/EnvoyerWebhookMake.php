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
 * Dispatché après la génération du planning, une modification manuelle
 * d'assignation, ou la création/modification/suppression d'un événement
 * synchronisé avec Google Calendar.
 *
 * Le payload peut être de deux types, distingués par la clé `type` :
 *   - absente ou "planning" : payload de créneaux (WebhookPayloadBuilder)
 *   - "evenement"            : payload d'événement (WebhookEvenementPayloadBuilder)
 */
class EnvoyerWebhookMake implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nombre de tentatives en cas d'échec */
    public int $tries = 3;

    /** Délai entre les tentatives (secondes) */
    public int $backoff = 60;

    public function __construct(
        private readonly array $payload
    ) {
    }

    /**
     * Exécution du job : envoi HTTP POST vers Make.com.
     */
    public function handle(): void
    {
        // config() fonctionne correctement après php artisan config:cache,
        // contrairement à env() qui retourne null en production.
        $url = config('services.make.webhook_url');

        if (empty($url)) {
            Log::warning('[WebhookMake] services.make.webhook_url non configurée — envoi ignoré.');
            return;
        }

        $type = $this->payload['type'] ?? 'planning';

        if ($type === 'evenement') {
            Log::info('[WebhookMake] Envoi du webhook événement vers Make.com', [
                'url'    => $url,
                'action' => $this->payload['action'] ?? null,
                'nom'    => $this->payload['evenement']['nom'] ?? null,
            ]);
        } else {
            Log::info('[WebhookMake] Envoi du webhook planning vers Make.com', [
                'url'         => $url,
                'nb_creneaux' => count($this->payload['creneaux'] ?? []),
            ]);
        }

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $this->payload);

        if ($response->successful()) {
            Log::info('[WebhookMake] Webhook envoyé avec succès.', [
                'status' => $response->status(),
                'type'   => $type,
            ]);

            audit('webhook', 'planning', null, null, [
                'url'         => $url,
                'status'      => $response->status(),
                'type'        => $type,
                'nb_creneaux' => count($this->payload['creneaux'] ?? []),
                'genere_le'   => $this->payload['genere_le'] ?? null,
            ]);
        } else {
            Log::error('[WebhookMake] Échec de l\'envoi du webhook.', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'type'   => $type,
            ]);

            // Déclenche une nouvelle tentative automatique
            $this->fail(new \RuntimeException(
                "Webhook Make.com échoué : HTTP {$response->status()}"
            ));
        }
    }
}
