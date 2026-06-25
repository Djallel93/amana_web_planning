<?php
// app/Notifications/CandidatureValideeNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée au membre quand un admin valide sa candidature.
 *
 * L'email utilise le template HTML brandé AMANA (emails/candidature-validee.blade.php)
 * et contient un lien pour créer son mot de passe (premier login via le système
 * de password reset Laravel).
 *
 * Le lien expire après 60 minutes (configuré dans config/auth.php).
 */
class CandidatureValideeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl
    ) {
    }

    /**
     * Canal de notification : email uniquement.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Contenu de l'email — rendu via le template Blade brandé AMANA.
     */
    public function toMail(object $notifiable): MailMessage
    {
        Log::info('[CandidatureValideeNotification] Préparation email', [
            'destinataire' => $notifiable->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
            'port' => config('mail.mailers.' . config('mail.default') . '.port'),
        ]);

        return (new MailMessage)
            ->subject('Bienvenue chez AMANA — Créez votre mot de passe')
            ->view(
                'emails.candidature-validee',
                [
                    'prenom' => $notifiable->prenom,
                    'resetUrl' => $this->resetUrl,
                ]
            );
    }

    /**
     * Appelé par Laravel quand le job de notification échoue définitivement.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[CandidatureValideeNotification] Échec définitif envoi email', [
            'erreur' => $exception->getMessage(),
            'classe' => get_class($exception),
        ]);
    }
}