<?php
// app/Notifications/CandidatureValideeDejaInscritNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée au membre quand un admin valide sa candidature,
 * MAIS que le compte possède déjà un mot de passe.
 *
 * ShouldQueue retiré intentionnellement — voir NouveauMembreNotification.
 */
class CandidatureValideeDejaInscritNotification extends Notification
{
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
        Log::info('[CandidatureValideeDejaInscritNotification] Envoi email', [
            'destinataire' => $notifiable->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
        ]);

        return (new MailMessage)
            ->subject('Votre accès AMANA Planning est activé')
            ->view('emails.candidature-validee-deja-inscrit', [
                'prenom' => $notifiable->prenom,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}