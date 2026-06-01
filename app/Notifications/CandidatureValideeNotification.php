<?php
// app/Notifications/CandidatureValideeNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée au membre quand un admin valide sa candidature.
 *
 * L'email contient un lien pour créer son mot de passe
 * (premier login via le système de password reset Laravel).
 *
 * Le lien expire après 60 minutes (configuré dans config/auth.php).
 */
class CandidatureValideeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $resetUrl
    ) {}

    /**
     * Canal de notification : email uniquement.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Contenu de l'email envoyé au nouveau membre.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenue sur AMANA Planning — Créez votre mot de passe')
            ->greeting('Bonjour ' . $notifiable->prenom . ',')
            ->line('Votre candidature sur AMANA Planning a été validée. Bienvenue dans l\'équipe !')
            ->line('Pour accéder à l\'application, vous devez d\'abord créer votre mot de passe en cliquant sur le bouton ci-dessous.')
            ->action('Créer mon mot de passe', $this->resetUrl)
            ->line('Ce lien est valable **60 minutes**. Passé ce délai, contactez un administrateur pour en obtenir un nouveau.')
            ->line('Une fois votre mot de passe créé, vous pourrez :')
            ->line('• Consulter le planning des permanences')
            ->line('• Télécharger le planning en PDF')
            ->line('• Gérer vos absences et vos disponibilités')
            ->salutation('À bientôt sur AMANA Planning !');
    }
}
