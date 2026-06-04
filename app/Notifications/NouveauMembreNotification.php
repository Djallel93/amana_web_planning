<?php
// app/Notifications/NouveauMembreNotification.php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Personne;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée à tous les admins planning
 * quand un nouveau membre soumet sa candidature.
 *
 * Utilise le template HTML brandé AMANA (emails/nouveau-membre.blade.php).
 * Implémente ShouldQueue pour ne pas bloquer la réponse HTTP.
 */
class NouveauMembreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Personne $candidat
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
        return (new MailMessage)
            ->subject(
                'Nouvelle candidature — '
                . $this->candidat->prenom . ' '
                . strtoupper($this->candidat->nom)
            )
            ->view(
                'emails.nouveau-membre',
                [
                    // Admin receiving the email
                    'adminPrenom' => $notifiable->prenom,

                    // The new candidat (with relations eager-loaded in the controller)
                    'candidat' => $this->candidat,

                    // Link to the candidatures management page
                    'urlValidation' => route('admin.candidatures.index'),
                ]
            );
    }
}