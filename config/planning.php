<?php
// config/planning.php
//
// Config propre à amana_web_planning (par opposition à config/amana-shared.php,
// qui porte le contrat partagé entre apps). N'existe que pour cette app.

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Restriction de tâches par rôle
    |--------------------------------------------------------------------------
    |
    | Certains rôles planning n'ont le droit d'assurer qu'un sous-ensemble
    | des tâches (ref_taches.code). Utilisé par Personne::peutFaireTache()
    | comme filtre appliqué AVANT la table `restrictions` — les préférences
    | personnelles de l'utilisateur (page Disponibilités) restent donc
    | intactes en base même pour les tâches hors périmètre de son rôle ;
    | elles sont simplement ignorées tant qu'il reste sur ce rôle.
    |
    | Un rôle absent de ce tableau (ex. 'membre', 'gestionnaire', 'admin')
    | n'a AUCUNE restriction liée au rôle : seule la table `restrictions`
    | s'applique pour lui, comme avant.
    |
    | Ajouté le 08/08/2026 pour le rôle 'benevole' — voir
    | PlanningApplicationSeeder pour l'enregistrement du rôle lui-même.
    |
    */
    'role_task_restrictions' => [
        'benevole' => ['entree', 'salle', 'amana_food'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verrous d'actions (double soumission / concurrence)
    |--------------------------------------------------------------------------
    |
    | Voir App\Services\VerrouAction.
    |
    |   ttl                       Durée de vie maximale d'un verrou, en secondes
    |                             (filet de sécurité si le process meurt en cours).
    |   attente_regeneration_auto Temps maximal, en secondes, qu'une régénération
    |                             AUTOMATIQUE (absence, événement) patiente quand une
    |                             autre génération est en cours, avant d'abandonner
    |                             avec le message « régénérez manuellement ».
    |
    */
    'verrou' => [
        'ttl' => 120,
        'attente_regeneration_auto' => 10,
    ],

];
