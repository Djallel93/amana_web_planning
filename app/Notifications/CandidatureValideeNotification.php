<?php
// app/Notifications/CandidatureValideeNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée au membre quand un admin valide sa candidature.
 *
 * ShouldQueue retiré intentionnellement — voir NouveauMembreNotification
 * pour l'explication complète. Envoi synchrone direct sur IONOS.
 */
class CandidatureValideeNotification extends Notification
{
    public function __construct(
        private readonly string $resetUrl
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        Log::info('[CandidatureValideeNotification] Envoi email', [
            'destinataire' => $notifiable->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
        ]);

        return (new MailMessage)
            ->subject('Bienvenue chez AMANA — Créez votre mot de passe')
            ->view('emails.candidature-validee', [
                'prenom' => $notifiable->prenom,
                'resetUrl' => $this->resetUrl,
            ]);
    }
}