<?php
// app/Notifications/CandidatureValideeDejaInscritNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée au membre quand un admin valide sa candidature,
 * MAIS que le compte possède déjà un mot de passe (personne déjà
 * inscrite sur une autre application AMANA).
 *
 * Dans ce cas on ne génère pas de lien de reset — on informe simplement
 * que le compte est activé et qu'il peut se connecter directement.
 */
class CandidatureValideeDejaInscritNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $loginUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        Log::info('[CandidatureValideeDejaInscritNotification] Préparation email', [
            'destinataire' => $notifiable->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
            'port' => config('mail.mailers.' . config('mail.default') . '.port'),
        ]);

        return (new MailMessage)
            ->subject('Votre accès AMANA Planning est activé')
            ->view(
                'emails.candidature-validee-deja-inscrit',
                [
                    'prenom' => $notifiable->prenom,
                    'loginUrl' => $this->loginUrl,
                ]
            );
    }

    /**
     * Appelé par Laravel quand le job de notification échoue définitivement.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[CandidatureValideeDejaInscritNotification] Échec définitif envoi email', [
            'erreur' => $exception->getMessage(),
            'classe' => get_class($exception),
        ]);
    }
}