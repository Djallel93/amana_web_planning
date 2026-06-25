<?php
// app/Notifications/NouveauMembreNotification.php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Personne;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

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
        Log::info('[NouveauMembreNotification] Préparation email', [
            'destinataire' => $notifiable->email,
            'candidat_id' => $this->candidat->id,
            'candidat_email' => $this->candidat->email,
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.' . config('mail.default') . '.host'),
            'port' => config('mail.mailers.' . config('mail.default') . '.port'),
        ]);

        return (new MailMessage)
            ->subject(
                'Nouvelle candidature — '
                . $this->candidat->prenom . ' '
                . strtoupper($this->candidat->nom)
            )
            ->view(
                'emails.nouveau-membre',
                [
                    // Admin recevant l'email
                    'adminPrenom' => $notifiable->prenom,

                    // Le nouveau candidat (relations eager-loaded dans le contrôleur)
                    'candidat' => $this->candidat,

                    // Lien vers la gestion des candidatures
                    'urlValidation' => route('admin.candidatures.index'),
                ]
            );
    }

    /**
     * Appelé par Laravel quand le job de notification échoue définitivement.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[NouveauMembreNotification] Échec définitif envoi email', [
            'candidat_id' => $this->candidat->id,
            'erreur' => $exception->getMessage(),
            'classe' => get_class($exception),
        ]);
    }
}