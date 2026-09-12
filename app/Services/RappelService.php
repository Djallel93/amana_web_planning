<?php
// app/Services/RappelService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Personne;
use App\Models\RappelEnvoye;
use App\Models\Tache;
use App\Notifications\RappelCreneauNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Envoie les rappels par email pour les créneaux assignés (planning) — objet
 * distinct de la synchronisation Google Calendar (SynchroniserGoogleCalendar) :
 * les événements Google Calendar créés par cette dernière n'ont plus
 * d'attendee/invitation (voir GoogleCalendarService::buildEventBody()),
 * cette classe est désormais l'unique canal de rappel personnel et ciblé.
 *
 * Trois types de rappel, chacun envoyé UNE SEULE fois par (créneau, tâche,
 * personne) grâce à plan_rappels_envoyes (voir RappelEnvoye) :
 *   - '3_jours'  : 3 jours avant la date du créneau
 *   - 'jour_j'   : le jour même
 *   - '3h_avant' : ~3h avant l'heure de début RÉELLE de la tâche (variable
 *                  par tâche — ex. rappel_sandwich commence à 08:00 fixe,
 *                  les autres tâches vers 20:00 — traité uniformément ici,
 *                  aucun cas particulier)
 *
 * Couvre toutes les tâches assignées (les 5 tâches principales +
 * rappel_sandwich + assistance_amana_food, via
 * WebhookPayloadBuilder::buildPourDate()) — PAS les événements sociaux
 * (annonce_cours, message_bot), qui n'ont jamais d'assignation.
 *
 * Appelée par deux commandes planifiées distinctes (fréquences différentes) :
 *   - amana:rappels-quotidiens (dailyAt('08:00'))     → envoyerRappelsQuotidiens()
 *   - amana:rappels-imminents  (everyFifteenMinutes()) → envoyerRappelsImminents()
 * Voir routes/console.php pour l'enregistrement du scheduler, et le README
 * pour le cron IONOS qui fait tourner `php artisan schedule:run`.
 */
class RappelService
{
    private const TYPE_3_JOURS = '3_jours';
    private const TYPE_JOUR_J = 'jour_j';
    private const TYPE_3H_AVANT = '3h_avant';

    /** Fenêtre de tolérance pour le rappel "3h avant" — doit couvrir l'intervalle entre deux exécutions (15 min) sans trou ni chevauchement double-comptage (la dédup sur plan_rappels_envoyes absorbe un éventuel chevauchement). */
    private const FENETRE_3H_AVANT_MINUTES = 15;

    private ?Collection $tacheIdsParCode = null;

    public function __construct(
        private readonly WebhookPayloadBuilder $builder,
    ) {
    }

    /**
     * Envoie les rappels "3 jours avant" (pour la date J+3) et "jour J"
     * (pour aujourd'hui) — appelée une fois par jour à 08:00.
     *
     * @return array{'3_jours': int, 'jour_j': int} Nombre de rappels envoyés par type.
     */
    public function envoyerRappelsQuotidiens(): array
    {
        return [
            self::TYPE_3_JOURS => $this->envoyerPourDate(now('Europe/Paris')->addDays(3)->toDateString(), self::TYPE_3_JOURS),
            self::TYPE_JOUR_J => $this->envoyerPourDate(now('Europe/Paris')->toDateString(), self::TYPE_JOUR_J),
        ];
    }

    /**
     * Envoie les rappels "3h avant" dont le seuil (heure de début - 3h)
     * tombe dans la fenêtre courante — appelée toutes les
     * FENETRE_3H_AVANT_MINUTES minutes. Vérifie aujourd'hui ET demain (une
     * tâche commençant tôt le matin peut avoir son seuil "3h avant" la
     * veille au soir).
     */
    public function envoyerRappelsImminents(): int
    {
        $maintenant = now('Europe/Paris');
        $count = 0;

        foreach ([$maintenant->toDateString(), $maintenant->clone()->addDay()->toDateString()] as $date) {
            $payload = $this->builder->buildPourDate($date);
            if (!$payload) {
                continue;
            }

            foreach ($this->itemsAssignes($payload) as $item) {
                if (empty($item['heure_debut'])) {
                    continue;
                }

                $debut = Carbon::parse("{$date} {$item['heure_debut']}", 'Europe/Paris');
                $seuil = $debut->clone()->subHours(3);
                $finFenetre = $seuil->clone()->addMinutes(self::FENETRE_3H_AVANT_MINUTES);

                if ($maintenant->between($seuil, $finFenetre)) {
                    if ($this->envoyer($payload['id_planning'], $item, self::TYPE_3H_AVANT, $date)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function envoyerPourDate(string $date, string $type): int
    {
        $payload = $this->builder->buildPourDate($date);
        if (!$payload) {
            return 0;
        }

        $count = 0;
        foreach ($this->itemsAssignes($payload) as $item) {
            if ($this->envoyer($payload['id_planning'], $item, $type, $date)) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<int, array> Lignes taches[] + evenements_speciaux[] ayant une assignation (email non vide). */
    private function itemsAssignes(array $payload): array
    {
        return collect($payload['taches'] ?? [])
            ->merge($payload['evenements_speciaux'] ?? [])
            ->filter(fn(array $item) => !empty($item['email']))
            ->values()
            ->all();
    }

    /**
     * Envoie un rappel pour un item donné, si pas déjà envoyé
     * (plan_rappels_envoyes). Retourne false sans lever d'exception en cas
     * d'échec (personne introuvable, code de tâche inconnu, échec d'envoi
     * email) — un rappel manqué ne doit jamais interrompre le traitement
     * des autres.
     */
    private function envoyer(int $idPlanning, array $item, string $type, string $date): bool
    {
        $idTache = $this->resolveTacheId($item['code']);
        if ($idTache === null) {
            Log::warning('[RappelService] Code de tâche inconnu — rappel ignoré.', ['code' => $item['code']]);
            return false;
        }

        $personne = Personne::where('email', $item['email'])->first();
        if (!$personne) {
            Log::warning('[RappelService] Aucune personne trouvée pour cet email — rappel ignoré.', [
                'email' => $item['email'],
                'code' => $item['code'],
            ]);
            return false;
        }

        $dejaEnvoye = RappelEnvoye::where([
            'id_planning' => $idPlanning,
            'id_tache' => $idTache,
            'id_personne' => $personne->id,
            'type_rappel' => $type,
        ])->exists();

        if ($dejaEnvoye) {
            return false;
        }

        try {
            $personne->notify(new RappelCreneauNotification($item, $type, $date));
        } catch (\Throwable $e) {
            Log::error('[RappelService] Échec envoi rappel.', [
                'email' => $item['email'],
                'code' => $item['code'],
                'type' => $type,
                'erreur' => $e->getMessage(),
            ]);
            return false;
        }

        RappelEnvoye::create([
            'id_planning' => $idPlanning,
            'id_tache' => $idTache,
            'id_personne' => $personne->id,
            'type_rappel' => $type,
            'envoye_at' => now('Europe/Paris'),
        ]);

        return true;
    }

    private function resolveTacheId(string $code): ?int
    {
        if ($this->tacheIdsParCode === null) {
            $this->tacheIdsParCode = Tache::pluck('id', 'code');
        }

        return $this->tacheIdsParCode->get($code);
    }
}
