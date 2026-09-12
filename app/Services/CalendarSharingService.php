<?php
// app/Services/CalendarSharingService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendrierGoogle;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Log;

/**
 * Partage automatiquement les calendriers Google Calendar marqués
 * `inclure_nouveaux_membres` (registre `ref_calendriers_google`, voir
 * /parametres) avec l'adresse email d'un bénévole dont la candidature vient
 * d'être validée — appelé depuis Admin\CandidaturesController::valider().
 *
 * Exécuté de façon SYNCHRONE (pas de ShouldQueue) : contrairement à
 * SynchroniserGoogleCalendar (planning/événements), l'admin doit voir le
 * résultat immédiatement dans la même réponse — un échec de partage
 * (calendrier mal configuré, accès Google refusé…) doit apparaître comme un
 * avertissement sur la page Candidatures, pas silencieusement en log
 * plusieurs secondes plus tard dans une queue. La validation de la
 * candidature elle-même n'est JAMAIS bloquée par un échec de partage — un
 * email personnel mal saisi ne doit pas empêcher un admin de valider un
 * bénévole.
 */
class CalendarSharingService
{
    /** Mapping code de rôle applicatif → rôle ACL Google Calendar. */
    private const ROLES_ACL = [
        'benevole' => 'reader',
        'membre' => 'reader',
        'gestionnaire' => 'writer',
        'admin' => 'owner',
    ];

    public function __construct(
        private readonly GoogleCalendarService $google,
    ) {
    }

    /**
     * Partage tous les calendriers actifs marqués `inclure_nouveaux_membres`
     * avec $email, au niveau d'accès correspondant à $roleCode.
     *
     * @return array<int, array{nom: string, calendar_id: string, erreur: string}>
     *         Liste des calendriers pour lesquels le partage a échoué — vide
     *         si tout s'est bien passé. Ne lève jamais d'exception : chaque
     *         échec est capturé individuellement pour ne pas empêcher le
     *         partage des autres calendriers ni la validation de la
     *         candidature par l'appelant.
     */
    public function partagerPourNouveauMembre(string $email, string $roleCode): array
    {
        if (!$this->google->isConfigured()) {
            Log::warning('[CalendarSharingService] GOOGLE_SERVICE_ACCOUNT_JSON_BASE64 non configurée — partage ignoré.', [
                'email' => $email,
            ]);
            return [];
        }

        $role = self::ROLES_ACL[$roleCode] ?? 'reader';

        $calendriers = CalendrierGoogle::where('actif', true)
            ->where('inclure_nouveaux_membres', true)
            ->get();

        if ($calendriers->isEmpty()) {
            return [];
        }

        $echecs = [];

        foreach ($calendriers as $calendrier) {
            try {
                $this->google->partagerAvecUtilisateur($calendrier->calendar_id, $email, $role);

                Log::info('[CalendarSharingService] Calendrier partagé avec succès.', [
                    'calendar_id' => $calendrier->calendar_id,
                    'email' => $email,
                    'role' => $role,
                ]);
            } catch (GoogleServiceException $e) {
                Log::error('[CalendarSharingService] Échec du partage d\'un calendrier.', [
                    'calendar_id' => $calendrier->calendar_id,
                    'nom' => $calendrier->nom,
                    'email' => $email,
                    'role' => $role,
                    'erreur' => $e->getMessage(),
                ]);

                $echecs[] = [
                    'nom' => $calendrier->nom,
                    'calendar_id' => $calendrier->calendar_id,
                    'erreur' => $this->messageErreurLisible($e),
                ];
            } catch (\Throwable $e) {
                Log::error('[CalendarSharingService] Erreur inattendue lors du partage d\'un calendrier.', [
                    'calendar_id' => $calendrier->calendar_id,
                    'nom' => $calendrier->nom,
                    'email' => $email,
                    'erreur' => $e->getMessage(),
                ]);

                $echecs[] = [
                    'nom' => $calendrier->nom,
                    'calendar_id' => $calendrier->calendar_id,
                    'erreur' => $e->getMessage(),
                ];
            }
        }

        return $echecs;
    }

    private function messageErreurLisible(GoogleServiceException $e): string
    {
        return match ($e->getCode()) {
            404 => 'calendrier introuvable',
            403 => 'accès refusé au compte de service',
            400 => 'adresse email invalide',
            default => "HTTP {$e->getCode()}",
        };
    }
}
