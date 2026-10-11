<?php
// app/Notifications/Echanges/EchangeInvalideNotification.php

declare(strict_types=1);

namespace App\Notifications\Echanges;

use Amana\Shared\Notifications\Concerns\EmbedsLogo;
use App\Models\Echange;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée aux DEUX parties quand le système annule lui-même une demande
 * d'échange devenue sans objet parce que le planning a changé :
 *
 *   - MOTIF_PLANNING_REGENERE : les créneaux concernés ont été supprimés par une
 *     régénération du planning (SchedulerMain) ;
 *   - MOTIF_AFFECTATION_MODIFIEE : à l'acceptation, l'un des deux créneaux n'est plus
 *     attribué à la personne prévue (EchangeService::executerEchange()).
 *
 * Aucune affectation n'est échangée. Distincte de EchangeAnnuleNotification, qui
 * signale une annulation VOLONTAIRE par le demandeur et ne part qu'à la cible.
 */
class EchangeInvalideNotification extends Notification
{
    use EmbedsLogo;

    public const MOTIF_PLANNING_REGENERE = 'planning_regenere';

    public const MOTIF_AFFECTATION_MODIFIEE = 'affectation_modifiee';

    public function __construct(
        private readonly Echange $echange,
        private readonly string $motif,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->embedLogo(new MailMessage())
            ->subject('Demande d\'échange annulée — AMANA Planning')
            ->view('emails.echanges.invalide', [
                'echange' => $this->echange,
                'motif' => $this->motif,
                'notifiable' => $notifiable,
                'urlPlanning' => route('mon-planning'),
                'logoCid' => $this->logoCid(),
            ]);
    }
}
