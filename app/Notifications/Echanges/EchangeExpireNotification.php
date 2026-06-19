<?php
// app/Notifications/Echanges/EchangeExpireNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use App\Models\Echange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée à A quand la demande expire sans réponse de B.
 */
class EchangeExpireNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Échange de créneau expiré — AMANA Planning')
            ->view('emails.echanges.expire', [
                'echange'     => $this->echange,
                'notifiable'  => $notifiable,
                'urlPlanning' => route('mon-planning'),
            ]);
    }
}
