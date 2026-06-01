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
 * Implémente ShouldQueue pour ne pas bloquer la réponse HTTP
 * pendant l'envoi de l'email.
 */
class NouveauMembreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Personne $candidat
    ) {}

    /**
     * Canal de notification : email uniquement.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Contenu de l'email envoyé aux admins.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $urlValidation = route('admin.candidatures.index');

        return (new MailMessage)
            ->subject('Nouvelle candidature — ' . $this->candidat->prenom . ' ' . strtoupper($this->candidat->nom))
            ->greeting('Bonjour ' . $notifiable->prenom . ',')
            ->line('Une nouvelle candidature vient d\'être soumise sur AMANA Planning.')
            ->line('**Candidat :** ' . $this->candidat->prenom . ' ' . strtoupper($this->candidat->nom))
            ->line('**Email :** ' . $this->candidat->email)
            ->line('**Téléphone :** ' . ($this->candidat->telephone ?? 'Non renseigné'))
            ->line('**Date d\'inscription :** ' . ($this->candidat->date_inscription_benevole?->locale('fr')->isoFormat('D MMMM YYYY') ?? 'Non renseignée'))
            ->action('Voir les candidatures en attente', $urlValidation)
            ->line('Vous pouvez valider ou refuser cette candidature depuis l\'interface d\'administration.')
            ->salutation('L\'équipe AMANA Planning');
    }
}
