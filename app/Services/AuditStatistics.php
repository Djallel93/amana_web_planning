<?php
// app/Services/AuditStatistics.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\AuditHelper;
use App\Models\AuditLog;
use App\Models\Personne;
use Illuminate\Support\Collection;

/**
 * Service de calcul des statistiques d'utilisation de l'application.
 *
 * Distinct de Statistics.php (équilibrage de la rotation des tâches) et de
 * Bilan (présence/collecte du jour) : ce service mesure l'activité dans
 * l'application elle-même — qui fait quoi, et à quelle fréquence — à partir
 * de la table audit_logs, déjà alimentée par le helper audit() dans toute
 * l'application. Aucune nouvelle table n'est nécessaire.
 */
class AuditStatistics
{
    /**
     * Calcule toutes les métriques d'activité pour une période donnée.
     *
     * @param string $from Date de début (incluse), format YYYY-MM-DD
     * @param string $to   Date de fin (incluse), format YYYY-MM-DD
     */
    public function computeAll(string $from, string $to): array
    {
        // Scopé à cette application — audit_logs est partagée entre plusieurs
        // apps AMANA (voir id_application).
        $logs = AuditLog::with('personne')
            ->where('id_application', AuditHelper::applicationId())
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        return [
            'serieParJour'      => $this->serieParJour($logs, $from, $to),
            'parModule'         => $this->repartitionPar($logs, 'module'),
            'parAction'         => $this->repartitionPar($logs, 'action'),
            'utilisateursActifs' => $this->utilisateursActifs($logs),
            'cartes'            => $this->cartes($logs),
        ];
    }

    /**
     * Nombre total d'actions par jour sur la période (pour le graphique en
     * courbe), y compris les jours à zéro pour ne pas casser l'échelle.
     */
    private function serieParJour(Collection $logs, string $from, string $to): array
    {
        $parJour = $logs->groupBy(fn(AuditLog $l) => $l->created_at->toDateString());

        $serie = [];
        $curseur = \Carbon\Carbon::parse($from);
        $fin = \Carbon\Carbon::parse($to);

        while ($curseur->lte($fin)) {
            $date = $curseur->toDateString();
            $serie[] = [
                'date'  => $date,
                'total' => $parJour->get($date, collect())->count(),
            ];
            $curseur->addDay();
        }

        return $serie;
    }

    /**
     * Répartition du nombre d'entrées par valeur d'un champ donné
     * ('module' ou 'action'), triée par fréquence décroissante.
     */
    private function repartitionPar(Collection $logs, string $champ): array
    {
        return $logs->groupBy($champ)
            ->map(fn(Collection $groupe, string $valeur) => [
                'valeur' => $valeur,
                'total'  => $groupe->count(),
            ])
            ->values()
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Top utilisateurs par nombre d'actions effectuées (hors actions
     * système, où user_id est null).
     */
    private function utilisateursActifs(Collection $logs, int $limite = 8): array
    {
        return $logs->filter(fn(AuditLog $l) => $l->user_id !== null)
            ->groupBy('user_id')
            ->map(function (Collection $groupe) {
                /** @var AuditLog $premiere */
                $premiere = $groupe->first();
                $personne = $premiere->personne;

                return [
                    'nom'   => $personne ? "{$personne->prenom} {$personne->nom}" : 'Personne supprimée',
                    'total' => $groupe->count(),
                ];
            })
            ->sortByDesc('total')
            ->take($limite)
            ->values()
            ->all();
    }

    /**
     * Cartes de synthèse : volume de connexions, d'échanges, et de
     * régénérations automatiques déclenchées par une absence.
     *
     * La distinction "régénération manuelle" vs "déclenchée par une
     * absence" se fait via le champ 'declencheur' que
     * AbsenceRegenerationService ajoute au payload 'after' des entrées
     * generate/planning qu'il crée.
     */
    private function cartes(Collection $logs): array
    {
        $connexions = $logs->where('action', 'login')->count();

        $echanges = $logs->where('module', 'echanges')->count();

        $generationsPlanning = $logs->where('module', 'planning')->where('action', 'generate');
        $regenerationsAbsence = $generationsPlanning
            ->filter(fn(AuditLog $l) => ($l->after['declencheur'] ?? null) === 'absence')
            ->count();

        return [
            'totalActions'          => $logs->count(),
            'connexions'            => $connexions,
            'echanges'              => $echanges,
            'generationsPlanning'   => $generationsPlanning->count(),
            'regenerationsAbsence'  => $regenerationsAbsence,
            'utilisateursDistincts' => $logs->pluck('user_id')->filter()->unique()->count(),
        ];
    }
}
