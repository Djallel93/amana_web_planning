<?php
// app/Notifications/Echanges/EchangeRefuseNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use App\Models\Echange;
use App\Notifications\Concerns\EmbedsLogo;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée à A quand B refuse l'échange.
 */
class EchangeRefuseNotification extends Notification
{
    use EmbedsLogo;

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
        return $this->embedLogo(new MailMessage)
            ->subject('Échange de créneau refusé — AMANA Planning')
            ->view('emails.echanges.refuse', [
                'echange' => $this->echange,
                'notifiable' => $notifiable,
                'urlPlanning' => route('mon-planning'),
                'logoCid' => $this->logoCid(),
            ]);
    }
}