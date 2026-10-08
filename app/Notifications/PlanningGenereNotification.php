<?php
// app/Notifications/PlanningGenereNotification.php

declare(strict_types=1);

namespace App\Notifications;

use Amana\Shared\Notifications\Concerns\EmbedsLogo;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification envoyée aux admins et gestionnaires du planning quand le planning
 * vient d'être généré — manuellement (Planning > Générer) ou automatiquement
 * (régénération suite à une absence ou à un événement).
 *
 * Gabarit : resources/views/emails/planning-genere.blade.php, habillé avec les
 * partials partagés d'amana_shared (_head/_header/_footer, logo en CID).
 *
 * ShouldQueue volontairement absent, comme les autres notifications de l'app (voir
 * NouveauMembreNotification) : envoi synchrone direct sur IONOS. L'appelant
 * (PlanningGenerationNotifier) rattrape et journalise toute exception : un échec
 * d'envoi ne doit JAMAIS faire échouer la génération elle-même.
 *
 * Tous les champs saisis par des utilisateurs (nom d'une personne, d'un événement)
 * sont affichés échappés par Blade — jamais en {!! !!}.
 */
class PlanningGenereNotification extends Notification
{
    use EmbedsLogo;

    public const DECLENCHEUR_MANUEL = 'manuel';

    public const DECLENCHEUR_ABSENCE = 'absence';

    public const DECLENCHEUR_EVENEMENT = 'evenement';

    /**
     * @param array{
     *     declencheur: string,
     *     detail: string|null,
     *     acteur: string|null,
     *     debut: Carbon,
     *     fin: Carbon,
     *     jours_generes: int,
     *     non_assignes: int,
     *     quand: Carbon,
     * } $contexte
     *        `detail` complète « suite à … » pour une régénération automatique
     *        (ex. « l'absence de Awa Diallo, du 2 au 5 octobre 2026 »).
     */
    public function __construct(private readonly array $contexte) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contexte;
        $manuel = $c['declencheur'] === self::DECLENCHEUR_MANUEL;
        $debut = $c['debut']->copy()->locale('fr');
        $fin = $c['fin']->copy()->locale('fr');

        Log::info('[PlanningGenereNotification] Envoi email', [
            'destinataire' => $notifiable->email,
            'declencheur' => $c['declencheur'],
        ]);

        $titre = $manuel ? 'Planning généré' : 'Planning régénéré';

        return $this->embedLogo(new MailMessage())
            ->subject($titre . ' — du ' . $debut->isoFormat('D MMM') . ' au ' . $fin->isoFormat('D MMM YYYY'))
            ->view('emails.planning-genere', [
                'prenom' => $notifiable->prenom,
                'titre' => $titre,
                'manuel' => $manuel,
                'detail' => $c['detail'],
                'acteur' => $c['acteur'],
                'periode' => $debut->isoFormat('dddd D MMMM') . ' → ' . $fin->isoFormat('dddd D MMMM YYYY'),
                'joursGeneres' => $c['jours_generes'],
                'nonAssignes' => $c['non_assignes'],
                'quand' => $c['quand']->copy()->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm'),
                'urlPlanning' => route('planning.index'),
                'logoCid' => $this->logoCid(),
            ]);
    }
}
