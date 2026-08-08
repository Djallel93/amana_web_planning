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

];
