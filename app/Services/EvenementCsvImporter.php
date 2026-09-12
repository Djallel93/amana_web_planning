<?php
// app/Services/EvenementCsvImporter.php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\GoogleCalendarColors;
use App\Models\CalendrierGoogle;
use App\Models\Evenement;
use App\Models\Tache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Import en masse d'événements organisationnels depuis un fichier CSV.
 *
 * Format attendu (délimiteur `;`, encodage UTF-8, ligne d'en-tête
 * obligatoire) :
 *
 *   nom;date_debut;date_fin;description;couleur;taches;calendriers
 *
 *   - nom, date_debut (AAAA-MM-JJ), date_fin (AAAA-MM-JJ) : obligatoires
 *   - description : optionnel, texte libre
 *   - couleur : optionnel — colorId Google Calendar ('1' à '11') ou nom
 *     français de la palette (ex. 'Tomate'), voir GoogleCalendarColors::PALETTE
 *   - taches : optionnel — codes ref_taches séparés par '|' (ex.
 *     'entree|mektaba') — vide = événement purement informatif, exactement
 *     comme le formulaire de création manuel
 *   - calendriers : optionnel — noms de calendriers (ref_calendriers_google.nom,
 *     PAS l'identifiant Google Calendar brut) séparés par '|', résolus en
 *     google_calendar_id de la même façon que EvenementsController::
 *     resolveCalendarNames() (résolution inverse)
 *
 * Comportement "tout ou rien" (voir docblock de validate()) : le fichier
 * entier est d'abord validé ligne par ligne, sans rien enregistrer. Si une
 * seule ligne est invalide, import() ne doit PAS être appelée — c'est à
 * l'appelant (EvenementsController::storeImport()) de vérifier que
 * validate() ne renvoie aucune erreur avant d'appeler import().
 */
class EvenementCsvImporter
{
    private const DELIMITER = ';';
    private const SUB_DELIMITER = '|';

    /** @var array<string, int> code ref_taches => id */
    private array $tacheIdsParCode;

    /** @var array<string, string> nom de calendrier en minuscules => calendar_id */
    private array $calendarIdsParNom;

    public function __construct()
    {
        $this->tacheIdsParCode = Tache::pluck('id', 'code')->all();
        $this->calendarIdsParNom = CalendrierGoogle::pluck('calendar_id', 'nom')
            ->mapWithKeys(fn($id, $nom) => [mb_strtolower(trim((string) $nom)) => $id])
            ->all();
    }

    /**
     * Parse et valide le fichier entier sans rien enregistrer en base.
     *
     * @return array{rows: array<int, array>, errors: array<int, array{ligne: int, erreurs: array<int, string>}>}
     *         `rows` contient les lignes déjà résolues (codes tâches → ids,
     *         noms calendriers → google_calendar_id) prêtes pour import().
     *         Si `errors` n'est pas vide, `rows` ne doit PAS être importée.
     */
    public function validate(UploadedFile $file): array
    {
        $handle = new \SplFileObject($file->getRealPath(), 'r');
        $handle->setFlags(
            \SplFileObject::READ_CSV
            | \SplFileObject::SKIP_EMPTY
            | \SplFileObject::READ_AHEAD
            | \SplFileObject::DROP_NEW_LINE
        );
        $handle->setCsvControl(self::DELIMITER);

        $header = $handle->fgetcsv();
        if ($header === false || $header === [null]) {
            return ['rows' => [], 'errors' => [['ligne' => 1, 'erreurs' => ['Fichier vide ou illisible.']]]];
        }

        $header = array_map(fn($h) => mb_strtolower(trim((string) $h)), $header);
        $manquantes = array_diff(['nom', 'date_debut', 'date_fin'], $header);
        if (!empty($manquantes)) {
            return ['rows' => [], 'errors' => [[
                'ligne' => 1,
                'erreurs' => ['Colonnes obligatoires manquantes : ' . implode(', ', $manquantes) . '.'],
            ]]];
        }

        $rows = [];
        $errors = [];
        $numeroLigne = 1; // La ligne 1 est l'en-tête.

        foreach ($handle as $ligneCsv) {
            $numeroLigne++;

            if ($ligneCsv === null || $ligneCsv === [null] || $ligneCsv === false) {
                continue; // Ligne vide (fin de fichier notamment).
            }

            $data = array_combine($header, array_pad($ligneCsv, count($header), null));
            [$row, $erreursLigne] = $this->validerLigne($data);

            if (!empty($erreursLigne)) {
                $errors[] = ['ligne' => $numeroLigne, 'erreurs' => $erreursLigne];
                continue;
            }

            $rows[] = $row;
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = ['ligne' => 1, 'erreurs' => ['Aucune ligne de données trouvée dans le fichier.']];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Crée effectivement les événements — à appeler uniquement après un
     * validate() dont `errors` est vide (import "tout ou rien").
     *
     * Chaque ligne suit le même chemin de création que
     * EvenementsController::store() (création + pivot tâches bloquées +
     * pivot calendriers + entrée d'audit), le tout dans une transaction
     * UNIQUE pour l'ensemble du fichier : si une ligne échoue de façon
     * inattendue en base (contrainte, etc.), tout est annulé — aucun
     * événement partiel n'est créé.
     *
     * La synchronisation Google Calendar (par événement) et la régénération
     * du planning restent volontairement HORS de cette méthode — ce sont
     * des effets de bord externes déclenchés par l'appelant une fois la
     * transaction validée (voir EvenementsController::storeImport()).
     *
     * @param array<int, array> $rows Lignes validées retournées par validate()
     * @return array<int, Evenement> Événements créés, relations
     *         tachesBloquees/calendriers déjà chargées
     */
    public function import(array $rows): array
    {
        return DB::transaction(function () use ($rows) {
            $evenements = [];

            foreach ($rows as $row) {
                $evenement = Evenement::create([
                    'nom' => $row['nom'],
                    'date_debut' => $row['date_debut'],
                    'date_fin' => $row['date_fin'],
                    'description' => $row['description'],
                    'couleur' => $row['couleur'],
                ]);

                $evenement->tachesBloquees()->sync($row['tache_ids']);

                foreach ($row['calendar_ids'] as $calendarId) {
                    $evenement->calendriers()->create([
                        'google_calendar_id' => $calendarId,
                        'calendar_name' => $row['calendar_noms'][$calendarId] ?? $calendarId,
                    ]);
                }

                audit('create', 'evenements', $evenement->id, null, array_merge(
                    $evenement->toArray(),
                    [
                        'taches_bloquees' => $row['tache_ids'],
                        'calendar_ids' => $row['calendar_ids'],
                        'import_csv' => true,
                    ]
                ));

                $evenements[] = $evenement->load('tachesBloquees', 'calendriers');
            }

            return $evenements;
        });
    }

    /**
     * @return array{0: array, 1: array<int, string>} [ligne résolue, erreurs]
     */
    private function validerLigne(array $data): array
    {
        $erreurs = [];

        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') {
            $erreurs[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 150) {
            $erreurs[] = 'Le nom ne doit pas dépasser 150 caractères.';
        }

        $dateDebut = trim((string) ($data['date_debut'] ?? ''));
        $dateFin = trim((string) ($data['date_fin'] ?? ''));

        $dateDebutValide = $this->estDateValide($dateDebut);
        $dateFinValide = $this->estDateValide($dateFin);

        if (!$dateDebutValide) {
            $erreurs[] = "La date de début « {$dateDebut} » est invalide (format attendu : AAAA-MM-JJ).";
        }
        if (!$dateFinValide) {
            $erreurs[] = "La date de fin « {$dateFin} » est invalide (format attendu : AAAA-MM-JJ).";
        }
        if ($dateDebutValide && $dateFinValide && $dateFin < $dateDebut) {
            $erreurs[] = 'La date de fin doit être après ou égale à la date de début.';
        }

        $description = trim((string) ($data['description'] ?? '')) ?: null;

        $couleurBrute = trim((string) ($data['couleur'] ?? ''));
        $couleur = null;
        if ($couleurBrute !== '') {
            $couleur = $this->resoudreCouleur($couleurBrute);
            if ($couleur === null) {
                $erreurs[] = "Couleur inconnue « {$couleurBrute} » (attendu : 1 à 11, ou un nom de la palette Google Calendar).";
            }
        }

        $tacheIds = [];
        $tachesBrutes = trim((string) ($data['taches'] ?? ''));
        if ($tachesBrutes !== '') {
            foreach (explode(self::SUB_DELIMITER, $tachesBrutes) as $code) {
                $code = trim($code);
                if ($code === '') {
                    continue;
                }
                if (!isset($this->tacheIdsParCode[$code])) {
                    $erreurs[] = "Code tâche inconnu « {$code} ».";
                    continue;
                }
                $tacheIds[] = $this->tacheIdsParCode[$code];
            }
        }

        $calendarIds = [];
        $calendarNoms = [];
        $calendriersBruts = trim((string) ($data['calendriers'] ?? ''));
        if ($calendriersBruts !== '') {
            foreach (explode(self::SUB_DELIMITER, $calendriersBruts) as $nomCalendrier) {
                $nomCalendrier = trim($nomCalendrier);
                if ($nomCalendrier === '') {
                    continue;
                }
                $cle = mb_strtolower($nomCalendrier);
                if (!isset($this->calendarIdsParNom[$cle])) {
                    $erreurs[] = "Calendrier inconnu « {$nomCalendrier} ».";
                    continue;
                }
                $id = $this->calendarIdsParNom[$cle];
                $calendarIds[] = $id;
                $calendarNoms[$id] = $nomCalendrier;
            }
        }

        $row = [
            'nom' => $nom,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'description' => $description,
            'couleur' => $couleur,
            'tache_ids' => array_values(array_unique($tacheIds)),
            'calendar_ids' => array_values(array_unique($calendarIds)),
            'calendar_noms' => $calendarNoms,
        ];

        return [$row, $erreurs];
    }

    private function estDateValide(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$annee, $mois, $jour] = array_map('intval', explode('-', $date));

        return checkdate($mois, $jour, $annee);
    }

    private function resoudreCouleur(string $valeur): ?string
    {
        if (isset(GoogleCalendarColors::PALETTE[$valeur])) {
            return $valeur;
        }

        foreach (GoogleCalendarColors::PALETTE as $id => $info) {
            if (mb_strtolower($info['nom']) === mb_strtolower($valeur)) {
                return $id;
            }
        }

        return null;
    }
}
