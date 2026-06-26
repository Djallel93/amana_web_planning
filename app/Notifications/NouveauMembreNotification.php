<?php
// app/Notifications/NouveauMembreNotification.php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Personne;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée à tous les admins planning
 * quand un nouveau membre soumet sa candidature.
 *
 * ShouldQueue retiré intentionnellement : sur IONOS shared hosting, il n'y a
 * pas de worker de queue persistant. Même avec QUEUE_CONNECTION=sync, le bus
 * de job encapsule les exceptions et les avale silencieusement, rendant le
 * diagnostic impossible. L'envoi synchrone direct est plus fiable et les
 * exceptions remontent normalement au contrôleur.
 */
class NouveauMembreNotification extends Notification
{
    public function __construct(
        private readonly Personne $candidat
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Recharger les relations au cas où le modèle a été instancié sans elles
        $candidat = $this->candidat->relationLoaded('restrictions')
            ? $this->candidat
            : $this->candidat->load(['restrictions.tache']);

        Log::info('[NouveauMembreNotification] Envoi email', [
            'destinataire' => $notifiable->email,
            'candidat_id' => $candidat->id,
            'candidat_email' => $candidat->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
        ]);

        return (new MailMessage)
            ->subject(
                'Nouvelle candidature — '
                . $candidat->prenom . ' '
                . strtoupper($candidat->nom)
            )
            ->view('emails.nouveau-membre', [
                'adminPrenom' => $notifiable->prenom,
                'candidat' => $candidat,
                'urlValidation' => route('admin.candidatures.index'),
            ]);
    }
}