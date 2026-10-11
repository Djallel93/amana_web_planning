<?php
// app/Services/EchangeService.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateHelper;
use App\Jobs\SynchroniserGoogleCalendar;
use App\Models\CreneauTache;
use App\Models\Echange;
use App\Models\Personne;
use App\Models\Tache;
use App\Notifications\Echanges\EchangeAccepteNotification;
use App\Notifications\Echanges\EchangeAnnuleNotification;
use App\Notifications\Echanges\EchangeDemandeNotification;
use App\Notifications\Echanges\EchangeExpireNotification;
use App\Notifications\Echanges\EchangeInvalideNotification;
use App\Notifications\Echanges\EchangeRefuseNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service métier pour les échanges de créneaux.
 *
 * Responsabilités :
 *   - Calculer les échanges possibles pour un slot donné
 *   - Créer une demande d'échange
 *   - Accepter / refuser via token
 *   - Approbation admin/gestionnaire
 *   - Annulation par le demandeur
 *   - Expiration des demandes en attente
 *   - Dispatch de la synchronisation Google Calendar (PATCH) une fois l'échange exécuté
 */
class EchangeService
{
    public function __construct(
        private readonly WebhookPayloadBuilder $webhookBuilder,
    ) {}

    /**
     * Retourne les slots futurs échangeables pour un slot donné (A).
     *
     * Un slot est échangeable si :
     *   - Il appartient à un autre membre actif
     *   - Sa date est dans le futur
     *   - Il concerne la même tâche (même code) — on peut aussi permettre
     *     n'importe quelle tâche, mais par défaut on restreint au même code
     *     pour garder la cohérence des compétences
     *   - Il n'est pas déjà impliqué dans un échange en_attente : seul le COUPLE
     *     (créneau, tâche) engagé est masqué — les autres tâches du même jour, et
     *     les autres jours, restent proposés
     *
     * @param  int    $creneauId  ID du créneau du demandeur
     * @param  int    $tacheId    ID de la tâche du demandeur
     * @param  int    $personneId ID du demandeur (à exclure)
     * @return Collection<CreneauTache>
     */
    public function slotsEchangeables(int $creneauId, int $tacheId, int $personneId): Collection
    {
        // Charger la tâche pour avoir son code
        $tache = Tache::findOrFail($tacheId);

        // Couples (créneau, tâche) déjà engagés dans un échange en attente, que ce soit
        // comme slot du demandeur ou comme slot cible.
        $enAttente = Echange::enAttente()->get([
            'id_creneau_demandeur', 'id_tache_demandeur', 'id_creneau_cible', 'id_tache_cible',
        ]);
        $slotsBloques = $enAttente
            ->map(fn(Echange $e) => $e->id_creneau_demandeur . ':' . $e->id_tache_demandeur)
            ->merge($enAttente->map(fn(Echange $e) => $e->id_creneau_cible . ':' . $e->id_tache_cible))
            ->unique()
            ->all();

        return CreneauTache::with(['creneau', 'tache', 'personne'])
            ->join('plan_creneaux', 'plan_creneaux.id', '=', 'plan_creneaux_taches.id_planning')
            ->join('ref_taches', 'ref_taches.id', '=', 'plan_creneaux_taches.id_tache')
            ->whereNotNull('plan_creneaux_taches.id_personne')
            ->where('plan_creneaux_taches.id_personne', '!=', $personneId)
            ->where('ref_taches.code', $tache->code)
            // « Aujourd'hui » dans le fuseau métier (Europe/Paris), pas en UTC.
            ->where('plan_creneaux.date', '>', DateHelper::aujourdhui()->toDateString())
            ->when($slotsBloques !== [], fn($q) => $q->whereRaw(
                "CONCAT(plan_creneaux_taches.id_planning, ':', plan_creneaux_taches.id_tache) NOT IN ("
                . implode(',', array_fill(0, count($slotsBloques), '?')) . ')',
                $slotsBloques,
            ))
            ->select('plan_creneaux_taches.*')
            ->orderBy('plan_creneaux.date')
            ->get();
    }

    /**
     * Crée une demande d'échange entre A et B.
     *
     * @throws \RuntimeException si les slots ne sont plus disponibles
     */
    public function creerDemande(
        int $personneAId,
        int $creneauAId,
        int $tacheAId,
        int $personneBId,
        int $creneauBId,
        int $tacheBId,
    ): Echange {
        // Vérifications de base
        $slotA = CreneauTache::where('id_planning', $creneauAId)
            ->where('id_tache', $tacheAId)
            ->where('id_personne', $personneAId)
            ->firstOrFail();

        $slotB = CreneauTache::with('creneau')
            ->where('id_planning', $creneauBId)
            ->where('id_tache', $tacheBId)
            ->where('id_personne', $personneBId)
            ->firstOrFail();

        // Le créneau de A ne doit pas être passé. Comparaison par DATE dans le fuseau
        // métier (Europe/Paris, via DateHelper) — comme le reste de la logique — et
        // non par instant UTC : le créneau du jour reste échangeable jusqu'à la fin
        // de la journée (voir aussi expires_at ci-dessous).
        if (DateHelper::estPasse($slotA->creneau->date->toDateString())) {
            throw new \RuntimeException('Votre créneau est déjà passé, impossible d\'initier un échange.');
        }

        // Pas déjà un échange en_attente sur l'un de ces deux slots, QUEL QUE SOIT le
        // rôle qu'il y joue : un slot déjà engagé comme CIBLE ne peut pas être
        // reproposé comme slot de DEMANDEUR (et inversement) — deux échanges en
        // attente sur le même créneau n'ont pas pu être acceptés tous les deux.
        $dejaEnCours = Echange::enAttente()
            ->where(function ($q) use ($creneauAId, $tacheAId, $creneauBId, $tacheBId) {
                foreach ([[$creneauAId, $tacheAId], [$creneauBId, $tacheBId]] as [$creneauId, $tacheId]) {
                    $q->orWhere(fn($q2) => $q2->where('id_creneau_demandeur', $creneauId)
                        ->where('id_tache_demandeur', $tacheId))
                        ->orWhere(fn($q2) => $q2->where('id_creneau_cible', $creneauId)
                            ->where('id_tache_cible', $tacheId));
                }
            })->exists();

        if ($dejaEnCours) {
            throw new \RuntimeException('Un échange est déjà en cours sur l\'un de ces créneaux.');
        }

        $echange = Echange::create([
            'id_personne_demandeur' => $personneAId,
            'id_creneau_demandeur' => $creneauAId,
            'date_creneau_demandeur' => $slotA->creneau->date->toDateString(),
            'id_tache_demandeur' => $tacheAId,
            'id_personne_cible' => $personneBId,
            'id_creneau_cible' => $creneauBId,
            'date_creneau_cible' => $slotB->creneau->date->toDateString(),
            'id_tache_cible' => $tacheBId,
            'statut' => Echange::STATUT_EN_ATTENTE,
            'token_accept' => Str::random(64),
            'token_refuse' => Str::random(64),
            // Expire à la FIN de la journée du créneau du demandeur, heure de Paris
            // (23:59:59 Europe/Paris), exprimée dans le fuseau de l'application (UTC)
            // pour que la colonne stocke bien l'instant voulu.
            'expires_at' => Carbon::parse($slotA->creneau->date->toDateString(), DateHelper::FUSEAU_METIER)
                ->endOfDay()
                ->setTimezone(config('app.timezone')),
        ]);

        audit('create', 'echanges', $echange->id, null, $echange->toArray());

        // Notifier B
        $personneB = Personne::findOrFail($personneBId);
        $personneB->notify(new EchangeDemandeNotification($echange));

        return $echange;
    }

    /**
     * Accepte un échange via son token.
     * Exécute le swap en base et notifie les deux parties.
     *
     * @throws \RuntimeException si le token est invalide ou l'échange est terminé
     */
    public function accepterParToken(string $token): Echange
    {
        $echange = Echange::where('token_accept', $token)
            ->with(['demandeur', 'cible', 'creneauDemandeur', 'creneauCible', 'tacheDemandeur', 'tacheCible'])
            ->firstOrFail();

        if (!$echange->isEnAttente()) {
            throw new \RuntimeException('Cet échange n\'est plus en attente.');
        }

        if ($echange->expires_at->isPast()) {
            $echange->update(['statut' => Echange::STATUT_EXPIRE]);
            throw new \RuntimeException('Ce lien a expiré.');
        }

        return $this->executerEchange($echange, null);
    }

    /**
     * Refuse un échange via son token.
     */
    public function refuserParToken(string $token): Echange
    {
        $echange = Echange::where('token_refuse', $token)
            ->with(['demandeur', 'cible'])
            ->firstOrFail();

        if (!$echange->isEnAttente()) {
            throw new \RuntimeException('Cet échange n\'est plus en attente.');
        }

        // Même règle que l'acceptation : un lien de refus périmé ne fonctionne plus.
        // Le statut n'est volontairement pas modifié ici : la commande planifiée
        // amana:expire-echanges (expirerEchanges()) passe l'échange à « expire » ET
        // prévient le demandeur.
        if ($echange->expires_at->isPast()) {
            throw new \RuntimeException('Ce lien a expiré.');
        }

        $avant = $echange->toArray();
        $echange->update(['statut' => Echange::STATUT_REFUSE]);

        audit('update', 'echanges', $echange->id, $avant, ['statut' => Echange::STATUT_REFUSE]);

        // Notifier A
        $echange->demandeur->notify(new EchangeRefuseNotification($echange));

        return $echange;
    }

    /**
     * Approuve un échange par un admin/gestionnaire (override).
     */
    public function approuverParAdmin(int $echangeId, int $adminId): Echange
    {
        $echange = Echange::with(['demandeur', 'cible', 'creneauDemandeur', 'creneauCible', 'tacheDemandeur', 'tacheCible'])
            ->findOrFail($echangeId);

        if (!$echange->isEnAttente()) {
            throw new \RuntimeException('Cet échange n\'est plus en attente.');
        }

        return $this->executerEchange($echange, $adminId);
    }

    /**
     * Refuse un échange par un admin/gestionnaire.
     */
    public function refuserParAdmin(int $echangeId, int $adminId): Echange
    {
        $echange = Echange::with(['demandeur', 'cible'])
            ->findOrFail($echangeId);

        if (!$echange->isEnAttente()) {
            throw new \RuntimeException('Cet échange n\'est plus en attente.');
        }

        $avant = $echange->toArray();
        $echange->update([
            'statut' => Echange::STATUT_REFUSE,
            'approuve_par' => $adminId,
        ]);

        audit('update', 'echanges', $echange->id, $avant, [
            'statut' => Echange::STATUT_REFUSE,
            'approuve_par' => $adminId,
        ]);

        $echange->demandeur->notify(new EchangeRefuseNotification($echange));

        return $echange;
    }

    /**
     * Annule un échange par le demandeur lui-même.
     */
    public function annulerParDemandeur(int $echangeId, int $personneId): Echange
    {
        $echange = Echange::with(['demandeur', 'cible'])
            ->findOrFail($echangeId);

        if ($echange->id_personne_demandeur !== $personneId) {
            throw new \RuntimeException('Vous ne pouvez annuler que vos propres demandes d\'échange.');
        }

        if (!$echange->isEnAttente()) {
            throw new \RuntimeException('Cet échange ne peut plus être annulé.');
        }

        $avant = $echange->toArray();
        $echange->update(['statut' => Echange::STATUT_ANNULE]);

        audit('update', 'echanges', $echange->id, $avant, ['statut' => Echange::STATUT_ANNULE]);

        // Notifier B que la demande est annulée
        $echange->cible->notify(new EchangeAnnuleNotification($echange));

        return $echange;
    }

    /**
     * Expire les échanges dont la date de créneau est passée.
     * Appelée par la commande planifiée amana:expire-echanges.
     *
     * @return int Nombre d'échanges expirés
     */
    public function expirerEchanges(): int
    {
        $expired = Echange::enAttente()
            ->where('expires_at', '<', now())
            ->with(['demandeur'])
            ->get();

        foreach ($expired as $echange) {
            $avant = $echange->toArray();
            $echange->update(['statut' => Echange::STATUT_EXPIRE]);
            audit('update', 'echanges', $echange->id, $avant, ['statut' => Echange::STATUT_EXPIRE]);
            $echange->demandeur->notify(new EchangeExpireNotification($echange));
        }

        return $expired->count();
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Exécute le swap en base de données dans une transaction.
     * Modifie plan_creneaux_taches : A prend le slot de B et vice-versa.
     *
     * @throws \RuntimeException si l'un des deux slots n'est plus attribué à la personne
     *         prévue (planning régénéré ou réaffecté depuis la demande) : l'échange est
     *         alors annulé, les deux parties sont prévenues et rien n'est échangé.
     */
    private function executerEchange(Echange $echange, ?int $approuvePar): Echange
    {
        $perime = false;

        DB::transaction(function () use ($echange, $approuvePar, &$perime) {
            $avant = $echange->toArray();

            // Les deux slots sont relus VERROUILLÉS et revérifiés : entre la demande et
            // l'acceptation, le planning a pu être régénéré ou réaffecté. Sans ce
            // contrôle, le swap écraserait le titulaire actuel d'un slot et donnerait
            // au demandeur un slot qu'il n'a plus.
            $slotDemandeur = CreneauTache::where('id_planning', $echange->id_creneau_demandeur)
                ->where('id_tache', $echange->id_tache_demandeur)
                ->lockForUpdate()
                ->first();
            $slotCible = CreneauTache::where('id_planning', $echange->id_creneau_cible)
                ->where('id_tache', $echange->id_tache_cible)
                ->lockForUpdate()
                ->first();

            if (
                $slotDemandeur === null || $slotCible === null
                || (int) $slotDemandeur->id_personne !== $echange->id_personne_demandeur
                || (int) $slotCible->id_personne !== $echange->id_personne_cible
            ) {
                // L'annulation est validée (pas de rollback) : l'exception est levée
                // APRÈS la transaction, une fois les deux parties prévenues.
                $perime = true;
                $echange->update(['statut' => Echange::STATUT_ANNULE]);

                audit('update', 'echanges', $echange->id, $avant, [
                    'statut' => Echange::STATUT_ANNULE,
                    'action' => 'annule_affectation_modifiee',
                ]);

                return;
            }

            // A prend le slot de B
            CreneauTache::where('id_planning', $echange->id_creneau_cible)
                ->where('id_tache', $echange->id_tache_cible)
                ->update(['id_personne' => $echange->id_personne_demandeur]);

            // B prend le slot de A
            CreneauTache::where('id_planning', $echange->id_creneau_demandeur)
                ->where('id_tache', $echange->id_tache_demandeur)
                ->update(['id_personne' => $echange->id_personne_cible]);

            $echange->update([
                'statut' => Echange::STATUT_ACCEPTE,
                'approuve_par' => $approuvePar,
            ]);

            audit('update', 'echanges', $echange->id, $avant, [
                'statut' => Echange::STATUT_ACCEPTE,
                'approuve_par' => $approuvePar,
                'action' => 'swap_executed',
            ]);
        });

        // Recharger après transaction
        $echange->refresh()->load(['demandeur', 'cible', 'creneauDemandeur', 'creneauCible', 'tacheDemandeur', 'tacheCible']);

        if ($perime) {
            $echange->demandeur->notify(new EchangeInvalideNotification($echange, EchangeInvalideNotification::MOTIF_AFFECTATION_MODIFIEE));
            $echange->cible->notify(new EchangeInvalideNotification($echange, EchangeInvalideNotification::MOTIF_AFFECTATION_MODIFIEE));

            throw new \RuntimeException(
                'Cet échange n\'est plus valable : le planning a changé depuis la demande. '
                . 'La demande a été annulée et aucun créneau n\'a été échangé.'
            );
        }

        // Notifier les deux parties
        $echange->demandeur->notify(new EchangeAccepteNotification($echange, 'demandeur'));
        $echange->cible->notify(new EchangeAccepteNotification($echange, 'cible'));

        // ── Synchronisation Google Calendar : PATCH avec les deux créneaux affectés ─
        $this->dispatchWebhookEchange($echange);

        return $echange;
    }

    /**
     * Dispatche un webhook PATCH pour les deux créneaux touchés par l'échange.
     * Silencieux en cas d'erreur (ne doit pas faire échouer l'échange lui-même).
     *
     * Les deux slots sont toujours envoyés, même si l'un est désormais dans
     * le passé — l'échange ayant réellement été exécuté en base.
     */
    private function dispatchWebhookEchange(Echange $echange): void
    {
        try {
            $payload = $this->webhookBuilder->buildForEchange(
                $echange->creneauCible,
                $echange->tacheCible,
                $echange->creneauDemandeur,
                $echange->tacheDemandeur,
            );

            SynchroniserGoogleCalendar::dispatch($payload, 'patch');

            Log::info('[EchangeService] Synchronisation Google Calendar dispatchée (échange exécuté)', [
                'echange_id' => $echange->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EchangeService] Échec dispatch synchronisation Google Calendar (échange)', [
                'echange_id' => $echange->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
