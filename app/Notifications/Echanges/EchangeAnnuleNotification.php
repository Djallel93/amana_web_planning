<?php
// app/Notifications/Echanges/EchangeAnnuleNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use App\Models\Echange;


use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée à B quand A annule sa demande d'échange.
 */
class EchangeAnnuleNotification extends Notification
{

    public function __construct(
        private readonly Echange $echange,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Demande d\'échange annulée — AMANA Planning')
            ->view('emails.echanges.annule', [
                'echange' => $this->echange,
                'notifiable' => $notifiable,
            ]);
    }
}