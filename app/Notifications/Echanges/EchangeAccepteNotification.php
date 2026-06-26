<?php
// app/Notifications/Echanges/EchangeAccepteNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use App\Models\Echange;


use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée aux deux parties quand un échange est accepté/exécuté.
 * Le rôle ('demandeur' ou 'cible') adapte le contenu de l'email.
 */
class EchangeAccepteNotification extends Notification
{

    public function __construct(
        private readonly Echange $echange,
        private readonly string $role, // 'demandeur' | 'cible'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Échange de créneau confirmé — AMANA Planning')
            ->view('emails.echanges.accepte', [
                'echange' => $this->echange,
                'notifiable' => $notifiable,
                'role' => $this->role,
                'urlPlanning' => route('mon-planning'),
            ]);
    }
}