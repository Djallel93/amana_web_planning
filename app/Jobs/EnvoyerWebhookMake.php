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
 * Dispatché après la génération du planning pour ne pas bloquer la réponse HTTP.
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
        $url = env('MAKE_WEBHOOK_URL');

        if (empty($url)) {
            Log::warning('[WebhookMake] MAKE_WEBHOOK_URL non configurée — envoi ignoré.');
            return;
        }

        Log::info('[WebhookMake] Envoi du webhook vers Make.com', [
            'url' => $url,
            'nb_creneaux' => count($this->payload['creneaux'] ?? []),
        ]);

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $this->payload);

        if ($response->successful()) {
            Log::info('[WebhookMake] Webhook envoyé avec succès.', [
                'status' => $response->status(),
            ]);

            audit('webhook', 'planning', null, null, [
                'url' => $url,
                'status' => $response->status(),
                'nb_creneaux' => count($this->payload['creneaux'] ?? []),
                'genere_le' => $this->payload['genere_le'] ?? null,
            ]);
        } else {
            Log::error('[WebhookMake] Échec de l\'envoi du webhook.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Déclenche une nouvelle tentative automatique
            $this->fail(new \RuntimeException(
                "Webhook Make.com échoué : HTTP {$response->status()}"
            ));
        }
    }
}