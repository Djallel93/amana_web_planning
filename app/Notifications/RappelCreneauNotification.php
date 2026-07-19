<?php
// app/Notifications/RappelCreneauNotification.php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\EmbedsLogo;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Rappel par email pour un créneau/tâche assigné — voir RappelService pour
 * la logique d'envoi/déduplication (3 jours avant / jour J / 3h avant).
 *
 * ShouldQueue volontairement absent, comme les autres notifications de
 * l'app (voir CandidatureValideeNotification) — envoi synchrone direct sur
 * IONOS, appelé depuis une commande planifiée donc pas de contrainte de
 * temps de réponse HTTP à respecter ici.
 */
class RappelCreneauNotification extends Notification
{
    use EmbedsLogo;

    /**
     * @param array $item Une ligne taches[]/evenements_speciaux[] de
     *        WebhookPayloadBuilder::buildPourDate() — code, nom, assigne,
     *        email, heure_debut, heure_fin, description.
     * @param string $type '3_jours' | 'jour_j' | '3h_avant'
     * @param string $date Date du créneau (Y-m-d).
     */
    public function __construct(
        private readonly array $item,
        private readonly string $type,
        private readonly string $date,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        Log::info('[RappelCreneauNotification] Envoi email', [
            'destinataire' => $notifiable->email,
            'code' => $this->item['code'],
            'type' => $this->type,
        ]);

        $date = Carbon::parse($this->date, 'Europe/Paris')->locale('fr');
        [$badge, $titleSub, $intro] = $this->contenuParType();

        return $this->embedLogo(new MailMessage)
            ->subject("Rappel — {$this->item['nom']} ({$date->isoFormat('dddd D MMMM')})")
            ->view('emails.rappel-creneau', [
                'prenom' => $notifiable->prenom,
                'badge' => $badge,
                'titleSub' => $titleSub,
                'intro' => $intro,
                'tache' => $this->item['nom'],
                'dateFormatee' => $date->isoFormat('dddd D MMMM'),
                'heureDebut' => $this->item['heure_debut'] ?? null,
                'heureFin' => $this->item['heure_fin'] ?? null,
                'description' => $this->item['description'] ?? '',
                'logoCid' => $this->logoCid(),
            ]);
    }

    /** @return array{0: string, 1: string, 2: string} [badge, titleSub, intro] */
    private function contenuParType(): array
    {
        return match ($this->type) {
            '3_jours' => [
                'Dans 3 jours',
                'Rappel anticipé',
                'Un petit rappel : vous êtes attendu(e) dans <strong>3 jours</strong> pour :',
            ],
            'jour_j' => [
                "Aujourd'hui",
                'Rappel du jour',
                "Un petit rappel : vous êtes attendu(e) <strong>aujourd'hui</strong> pour :",
            ],
            '3h_avant' => [
                'Dans 3 heures',
                'Rappel imminent',
                'Un petit rappel : vous êtes attendu(e) dans <strong>3 heures</strong> pour :',
            ],
            default => ['Rappel', 'Rappel de créneau', 'Un petit rappel pour votre créneau :'],
        };
    }
}
