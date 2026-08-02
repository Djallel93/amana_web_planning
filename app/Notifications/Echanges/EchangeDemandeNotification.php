<?php
// app/Notifications/Echanges/EchangeDemandeNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use App\Models\Echange;
use App\Notifications\Concerns\EmbedsLogo;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée à B quand A demande un échange.
 * Contient les liens accept/refuse tokenisés.
 */
class EchangeDemandeNotification extends Notification
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
            ->subject('Demande d\'échange de créneau — AMANA Planning')
            ->view('emails.echanges.demande', [
                'echange' => $this->echange,
                'notifiable' => $notifiable,
                'urlAccept' => route('echanges.accepter', $this->echange->token_accept),
                'urlRefuse' => route('echanges.refuser', $this->echange->token_refuse),
                'logoCid' => $this->logoCid(),
            ]);
    }
}