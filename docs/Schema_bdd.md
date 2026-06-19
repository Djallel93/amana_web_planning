# Base de données — AMANA Planning

## Table des matières

1. [Schéma général](#schéma-général)
2. [Diagramme des relations](#diagramme-des-relations)
3. [Description des tables](#description-des-tables)

---

## Schéma général

La base de données est organisée en trois groupes fonctionnels :

| Groupe          | Préfixe          | Rôle                                                                                |
| --------------- | ---------------- | ----------------------------------------------------------------------------------- |
| **Référentiel** | `ref_`           | Données de configuration stables (personnes, rôles, tâches, paramètres, événements) |
| **Planning**    | `plan_`          | Données opérationnelles (créneaux, assignations, absences, restrictions, échanges)  |
| **Système**     | _(sans préfixe)_ | Tables techniques Laravel (sessions, jobs, cache, audit)                            |

> **Aucune migration n'a été ajoutée** pour les fonctionnalités « Mon planning », « Avertissement de chevauchement » et « Dry-run preview ». Ces trois fonctionnalités sont purement applicatives et n'ajoutent aucune table ni colonne.
>
> **Deux migrations ont été ajoutées** pour les fonctionnalités décrites ci-dessous :
>
> - `ref_evenements.calendar_name` — synchronisation Google Calendar par événement
> - `plan_echanges` — échanges de créneaux entre membres

---

## Diagramme des relations

```mermaid
erDiagram

    ref_applications {
        tinyint id PK
        varchar code
        varchar libelle
        boolean actif
    }

    ref_roles {
        tinyint id PK
        varchar code
        varchar libelle
        tinyint id_application FK
    }

    ref_personnes {
        int id PK
        varchar nom
        varchar prenom
        varchar email
        varchar password
        varchar telephone
        date date_debut_planning
        enum statut
        timestamp email_verified_at
        timestamp derniere_maj
    }

    ref_personnes_roles {
        int id_personne FK
        tinyint id_role FK
        date date_attribution
    }

    ref_taches {
        tinyint id PK
        varchar code
        varchar libelle
        text description
        boolean actif
    }

    ref_evenements {
        int id PK
        varchar nom
        date date_debut
        date date_fin
        text description
        varchar calendar_name
    }

    ref_evenements_taches {
        int id_evenement FK
        tinyint id_tache FK
    }

    ref_settings {
        tinyint id PK
        tinyint id_application FK
        varchar cle
        varchar valeur
        enum type
        varchar libelle
        text description
    }

    plan_creneaux {
        int id PK
        date date
    }

    plan_creneaux_taches {
        int id_planning FK
        tinyint id_tache FK
        int id_personne FK
    }

    plan_creneaux_evenements {
        int id_planning FK
        int id_evenement FK
    }

    plan_absences {
        int id PK
        int id_personne FK
        date date_debut
        date date_fin
        varchar raison
    }

    plan_restrictions {
        int id PK
        int id_personne FK
        int id_tache FK
        enum jour
        boolean autorise
    }

    plan_echanges {
        int id PK
        int id_personne_demandeur FK
        int id_creneau_demandeur FK
        tinyint id_tache_demandeur FK
        int id_personne_cible FK
        int id_creneau_cible FK
        tinyint id_tache_cible FK
        enum statut
        varchar token_accept
        varchar token_refuse
        timestamp expires_at
        int approuve_par FK
        timestamp created_at
        timestamp updated_at
    }

    audit_logs {
        bigint id PK
        varchar action
        varchar module
        bigint entity_id
        json before
        json after
        varchar ip_address
        timestamp created_at
    }

    sessions {
        varchar id PK
        int user_id
        varchar ip_address
        longtext payload
        int last_activity
    }

    ref_applications ||--o{ ref_roles : "possède"
    ref_applications ||--o{ ref_settings : "configure"
    ref_roles ||--o{ ref_personnes_roles : "attribué via"
    ref_personnes ||--o{ ref_personnes_roles : "a"
    ref_personnes ||--o{ plan_creneaux_taches : "assignée dans"
    ref_personnes ||--o{ plan_absences : "a"
    ref_personnes ||--o{ plan_restrictions : "a"
    ref_personnes ||--o{ plan_echanges : "demande (demandeur)"
    ref_personnes ||--o{ plan_echanges : "cible (cible)"
    ref_personnes ||--o{ plan_echanges : "approuve (approuve_par)"
    ref_taches ||--o{ plan_creneaux_taches : "utilisée dans"
    ref_taches ||--o{ plan_restrictions : "concerne"
    ref_taches ||--o{ ref_evenements_taches : "bloquée par"
    ref_taches ||--o{ plan_echanges : "concernée par (demandeur/cible)"
    ref_evenements ||--o{ ref_evenements_taches : "bloque"
    ref_evenements ||--o{ plan_creneaux_evenements : "liée à"
    plan_creneaux ||--o{ plan_creneaux_taches : "contient"
    plan_creneaux ||--o{ plan_creneaux_evenements : "associé à"
    plan_creneaux ||--o{ plan_echanges : "concerné par (demandeur/cible)"
```

---

## Description des tables

### `ref_applications`

Référentiel des applications du système AMANA partageant la même base de données. Chaque application possède ses propres rôles et paramètres.

| Colonne   | Type               | Description                                            |
| --------- | ------------------ | ------------------------------------------------------ |
| `id`      | TINYINT PK         | Identifiant auto-incrémenté                            |
| `code`    | VARCHAR(50) UNIQUE | Identifiant technique (`planning`, `livraisons`…)      |
| `libelle` | VARCHAR(100)       | Nom lisible                                            |
| `actif`   | BOOLEAN            | Permet de désactiver une application sans la supprimer |

---

### `ref_roles`

Rôles disponibles par application. Un rôle est toujours rattaché à une application spécifique.

| Colonne          | Type         | Description                                                    |
| ---------------- | ------------ | -------------------------------------------------------------- |
| `id`             | TINYINT PK   | Identifiant                                                    |
| `code`           | VARCHAR(50)  | Code technique (`admin`, `gestionnaire`, `membre`, `benevole`) |
| `libelle`        | VARCHAR(100) | Libellé affiché                                                |
| `id_application` | TINYINT FK   | Application propriétaire du rôle                               |

> Contrainte unique : `(code, id_application)`.

---

### `ref_personnes`

Table centrale — remplace la table `users` standard de Laravel. Contient tous les membres, bénévoles et candidats.

| Colonne               | Type                 | Description                                                                                  |
| --------------------- | -------------------- | -------------------------------------------------------------------------------------------- |
| `id`                  | INT PK               | Identifiant                                                                                  |
| `nom`                 | VARCHAR(100)         | Nom de famille                                                                               |
| `prenom`              | VARCHAR(100)         | Prénom                                                                                       |
| `email`               | VARCHAR(255) UNIQUE  | Email = identifiant de connexion                                                             |
| `password`            | VARCHAR NULLABLE     | Hash bcrypt — NULL tant que le membre n'a pas créé son mot de passe via le lien d'invitation |
| `remember_token`      | VARCHAR              | Token "se souvenir de moi" (standard Laravel)                                                |
| `email_verified_at`   | TIMESTAMP NULLABLE   | Date de vérification email — renseigné à la création du mot de passe                         |
| `telephone`           | VARCHAR(20) NULLABLE | Numéro de téléphone                                                                          |
| `date_debut_planning` | DATE NULLABLE        | Date d'entrée dans la rotation — les créneaux antérieurs à cette date ne sont pas assignés   |
| `statut`              | ENUM                 | `En attente` / `Validé` / `Suspendu` / `Archivé`                                             |
| `derniere_maj`        | TIMESTAMP            | Mis à jour automatiquement à chaque modification                                             |

> **Seules les personnes avec `statut = Validé` sont incluses dans la génération du planning.**
> `date_debut_planning` est utilisée par l'algorithme pour ne pas assigner une nouvelle recrue avant son arrivée effective.
>
> **Échanges de créneaux :** une personne peut apparaître dans `plan_echanges` à trois titres possibles — `id_personne_demandeur` (elle initie l'échange), `id_personne_cible` (on lui propose un échange) ou `approuve_par` (elle a approuvé l'échange en tant qu'admin/gestionnaire).

---

### `ref_personnes_roles`

Table pivot N-N entre personnes et rôles. Une personne ne peut avoir qu'un seul rôle par application.

| Colonne            | Type       | Description                    |
| ------------------ | ---------- | ------------------------------ |
| `id_personne`      | INT FK     | Référence vers `ref_personnes` |
| `id_role`          | TINYINT FK | Référence vers `ref_roles`     |
| `date_attribution` | DATE       | Date d'attribution du rôle     |

> Clé primaire composite : `(id_personne, id_role)`.

---

### `ref_taches`

Référentiel des tâches planifiables.

| Colonne       | Type               | Description                                                           |
| ------------- | ------------------ | --------------------------------------------------------------------- |
| `id`          | TINYINT PK         | Identifiant                                                           |
| `code`        | VARCHAR(50) UNIQUE | Code technique (`entree`, `mektaba`, `salle`, `amana_food`, `cours`…) |
| `libelle`     | VARCHAR(100)       | Libellé affiché                                                       |
| `description` | TEXT NULLABLE      | Description envoyée dans le payload webhook                           |
| `actif`       | BOOLEAN            | `true` = incluse dans la rotation du scheduler                        |

**Tâches actives (rotation) :** `entree`, `mektaba`, `salle`, `amana_food`, `cours`

**Tâches inactives (webhook uniquement) :** `rappel_sandwich`, `assistance_amana_food`, `annonce_cours`, `message_general`

> **Échanges de créneaux :** `id_tache_demandeur` et `id_tache_cible` dans `plan_echanges` référencent cette table. Un échange ne peut concerner que des tâches actives déjà assignées dans le planning — le service `EchangeService` restreint par défaut les slots échangeables à la **même tâche** (même `code`) que le slot d'origine.

---

### `ref_evenements`

Événements organisationnels (vacances, Ramadan, jours fériés…). Peut optionnellement être synchronisé avec Google Calendar via Make.com.

| Colonne         | Type                  | Description                                                               |
| --------------- | --------------------- | ------------------------------------------------------------------------- |
| `id`            | INT PK                | Identifiant                                                               |
| `nom`           | VARCHAR(150)          | Nom de l'événement                                                        |
| `date_debut`    | DATE                  | Début de la période                                                       |
| `date_fin`      | DATE                  | Fin de la période (incluse)                                               |
| `description`   | TEXT NULLABLE         | Notes complémentaires                                                     |
| `calendar_name` | VARCHAR(200) NULLABLE | Nom du calendrier Google Calendar cible — `NULL` = pas de synchronisation |

> Index sur `(date_debut, date_fin)` pour les recherches de chevauchement.
>
> **Synchronisation Google Calendar (`calendar_name`) :**
> Si cette colonne est renseignée, `EvenementsController` dispatche un job `EnvoyerWebhookMake` vers Make.com à chaque `create`, `update` ou `delete` de l'événement, via `WebhookEvenementPayloadBuilder`. Si elle est vide, aucun webhook n'est envoyé pour cet événement. Cette colonne est **propre à chaque événement** — contrairement aux clés `calendar_*` de `ref_settings` qui configurent les calendriers des **tâches du planning** (entree, mektaba, etc.), pas des événements organisationnels.

---

### `ref_evenements_taches`

Table pivot N-N entre événements et tâches bloquées. Si un événement n'a aucune entrée ici, il est purement informatif et n'affecte pas la génération.

| Colonne        | Type       | Description                     |
| -------------- | ---------- | ------------------------------- |
| `id_evenement` | INT FK     | Référence vers `ref_evenements` |
| `id_tache`     | TINYINT FK | Référence vers `ref_taches`     |

> Clé primaire composite : `(id_evenement, id_tache)`.

---

### `ref_settings`

Paramètres de configuration par application — paires clé/valeur typées.

| Colonne          | Type                | Description                                                                  |
| ---------------- | ------------------- | ---------------------------------------------------------------------------- |
| `id`             | TINYINT PK          | Identifiant                                                                  |
| `id_application` | TINYINT FK NULLABLE | Application concernée (NULL = global)                                        |
| `cle`            | VARCHAR(100)        | Clé du paramètre (`heure_cours`, `offset_entree_debut`…)                     |
| `valeur`         | VARCHAR(500)        | Valeur stockée sous forme de chaîne                                          |
| `type`           | ENUM                | `string` / `integer` / `time` / `boolean` — utilisé pour le cast automatique |
| `libelle`        | VARCHAR(200)        | Libellé affiché dans l'UI                                                    |
| `description`    | TEXT NULLABLE       | Explication détaillée                                                        |

> Contrainte unique : `(id_application, cle)`.
> Le modèle `Setting` inclut un cache statique par requête HTTP pour éviter les requêtes N+1.

---

### `plan_creneaux`

Un créneau = une date de permanence (vendredi ou samedi). Une seule ligne par date.

| Colonne | Type        | Description           |
| ------- | ----------- | --------------------- |
| `id`    | INT PK      | Identifiant           |
| `date`  | DATE UNIQUE | Date de la permanence |

> L'accesseur `jour` retourne `"Vendredi"` ou `"Samedi"`. L'accesseur `semaine` retourne le numéro de semaine ISO.
>
> **Vue Mon planning :** `MonPlanningController` interroge cette table via un `JOIN` sur `plan_creneaux_taches` filtré par `id_personne = auth()->id()`, sans aucune colonne supplémentaire.
>
> **Échanges de créneaux :** `id_creneau_demandeur` et `id_creneau_cible` dans `plan_echanges` référencent cette table. La suppression d'un créneau (`onDelete('cascade')`) supprime également tout échange en cours qui le concerne.

---

### `plan_creneaux_taches`

Cœur du planning : lie un créneau à une tâche et à la personne assignée.

| Colonne       | Type            | Description                                   |
| ------------- | --------------- | --------------------------------------------- |
| `id_planning` | INT FK          | Référence vers `plan_creneaux`                |
| `id_tache`    | TINYINT FK      | Référence vers `ref_taches`                   |
| `id_personne` | INT FK NULLABLE | Personne assignée — NULL = tâche non assignée |

> Clé primaire composite : `(id_planning, id_tache)`.
> La suppression d'un créneau déclenche la suppression en cascade de toutes ses assignations.
>
> **En mode dry-run**, des lignes sont temporairement insérées dans cette table le temps du calcul, puis supprimées par le rollback de transaction. Aucun effet visible en dehors de la transaction.
>
> **Échanges de créneaux :** un échange accepté modifie directement deux lignes de cette table — la colonne `id_personne` du slot du demandeur prend l'ID de la personne cible, et vice-versa. Cette opération est exécutée par `EchangeService::executerEchange()` dans une transaction DB. Aucune colonne supplémentaire n'a été ajoutée à cette table ; l'historique de l'échange vit entièrement dans `plan_echanges`.

---

### `plan_creneaux_evenements`

Table pivot N-N entre créneaux et événements actifs à la date du créneau. Peuplée lors de la génération.

| Colonne        | Type   | Description                     |
| -------------- | ------ | ------------------------------- |
| `id_planning`  | INT FK | Référence vers `plan_creneaux`  |
| `id_evenement` | INT FK | Référence vers `ref_evenements` |

> Clé primaire composite : `(id_planning, id_evenement)`.

---

### `plan_absences`

Périodes d'absence des membres. Une personne absente n'est pas assignée lors de la génération pour les créneaux couverts.

| Colonne       | Type                  | Description                |
| ------------- | --------------------- | -------------------------- |
| `id`          | INT PK                | Identifiant                |
| `id_personne` | INT FK                | Personne concernée         |
| `date_debut`  | DATE                  | Début de l'absence         |
| `date_fin`    | DATE                  | Fin de l'absence (incluse) |
| `raison`      | VARCHAR(255) NULLABLE | Motif libre                |

> Index sur `id_personne` et `(date_debut, date_fin)`.

---

### `plan_restrictions`

Disponibilités par personne, tâche et jour de la semaine.

| Colonne       | Type       | Description                                               |
| ------------- | ---------- | --------------------------------------------------------- |
| `id`          | INT PK     | Identifiant                                               |
| `id_personne` | INT FK     | Personne concernée                                        |
| `id_tache`    | TINYINT FK | Tâche concernée                                           |
| `jour`        | ENUM       | `Lundi` à `Dimanche`                                      |
| `autorise`    | BOOLEAN    | `true` = peut faire la tâche ce jour / `false` = interdit |

> Contrainte unique : `(id_personne, id_tache, jour)`.
> **Comportement par défaut :** si aucune ligne n'existe pour une combinaison, la personne est considérée **disponible**. Une ligne n'est créée que pour exprimer une contrainte explicite.

---

### `plan_echanges`

Demandes d'échange de créneau entre deux membres. Un échange permet à un membre (le demandeur, "A") d'échanger un de ses créneaux futurs contre celui d'un autre membre (la cible, "B") assigné à la même tâche.

| Colonne                   | Type               | Description                                                                                       |
| ------------------------- | ------------------ | ------------------------------------------------------------------------------------------------- |
| `id`                      | INT PK             | Identifiant                                                                                       |
| `id_personne_demandeur`   | INT FK             | Personne qui demande l'échange (A)                                                                |
| `id_creneau_demandeur`    | INT FK             | `id_planning` du créneau de A                                                                     |
| `id_tache_demandeur`      | TINYINT FK         | `id_tache` du slot de A                                                                           |
| `id_personne_cible`       | INT FK             | Personne avec qui échanger (B)                                                                    |
| `id_creneau_cible`        | INT FK             | `id_planning` du créneau de B                                                                     |
| `id_tache_cible`          | TINYINT FK         | `id_tache` du slot de B                                                                           |
| `statut`                  | ENUM               | `en_attente` / `accepte` / `refuse` / `expire` / `annule`                                         |
| `token_accept`            | VARCHAR(64) UNIQUE | Token à usage unique pour le lien email « Accepter »                                              |
| `token_refuse`            | VARCHAR(64) UNIQUE | Token à usage unique pour le lien email « Refuser »                                               |
| `expires_at`              | TIMESTAMP          | Date d'expiration = date du créneau de A (fin de journée) — passé ce délai, la demande expire     |
| `approuve_par`            | INT FK NULLABLE    | ID de l'admin/gestionnaire si l'échange a été approuvé manuellement (override de la réponse de B) |
| `created_at`/`updated_at` | TIMESTAMP          | Horodatage standard                                                                               |

> Index sur `id_personne_demandeur`, `id_personne_cible`, `statut`, `expires_at`.
>
> **Cycle de vie du statut :**
>
> ```txt
> en_attente ──► accepte   (B accepte via token, OU admin/gestionnaire approuve)
>            ──► refuse    (B refuse via token, OU admin/gestionnaire refuse)
>            ──► expire    (date du créneau de A dépassée sans réponse — commande planifiée)
>            ──► annule    (le demandeur A annule lui-même sa demande)
> ```
>
> **Exécution de l'échange (statut → `accepte`) :** dans une transaction DB, `EchangeService::executerEchange()` met à jour deux lignes de `plan_creneaux_taches` — le slot de A reçoit `id_personne = id_personne_cible` et le slot de B reçoit `id_personne = id_personne_demandeur`. Les deux parties reçoivent un email de confirmation. Si l'échange est approuvé par un admin/gestionnaire avant la réponse de B, cette approbation **prend le pas** sur l'attente.
>
> **Tokens à usage unique :** une fois l'échange dans un statut terminal (`accepte`, `refuse`, `expire`, `annule`), les tokens ne permettent plus aucune action — `EchangeController::accepter()`/`refuser()` vérifient `isEnAttente()` avant d'exécuter quoi que ce soit. Pour échanger à nouveau (y compris un swap retour), une **nouvelle demande** doit être créée — aucun token n'est jamais réutilisé.
>
> **Expiration automatique :** la commande planifiée `php artisan amana:expire-echanges` (enregistrée dans `routes/console.php`, exécutée quotidiennement) marque `expire` tout échange `en_attente` dont `expires_at` est dépassé, puis notifie le demandeur.
>
> **Restriction de cohérence :** `EchangeService::slotsEchangeables()` ne propose que des slots futurs, assignés, sur la **même tâche** (`ref_taches.code` identique), appartenant à une personne différente du demandeur, et non déjà impliqués dans un autre échange `en_attente`.
>
> **Hors scope (non couvert par cette table) :** le cas où la personne cible B n'a **aucun créneau futur généré** sur la même tâche. Il n'y a alors aucun slot à échanger — une éventuelle fonctionnalité de « priorité de réassignation à la prochaine génération » serait portée par une table/feature distincte.
>
> **Audit :** chaque changement de statut appelle `audit('update', 'echanges', ...)` avec l'état avant/après — visible dans `audit_logs` au même titre que les autres actions sensibles de l'application.

---

### `audit_logs`

Journal d'audit de toutes les actions sensibles.

| Colonne       | Type             | Description                                                                                       |
| ------------- | ---------------- | ------------------------------------------------------------------------------------------------- |
| `id`          | BIGINT PK        | Identifiant                                                                                       |
| `action`      | VARCHAR(100)     | `create`, `update`, `delete`, `generate`, `login`, `logout`, `webhook`                            |
| `module`      | VARCHAR(100)     | `personnes`, `planning`, `restrictions`, `absences`, `evenements`, `auth`, `settings`, `echanges` |
| `entity_id`   | BIGINT NULLABLE  | ID de l'entité modifiée                                                                           |
| `entity_type` | VARCHAR NULLABLE | Classe du modèle (usage futur)                                                                    |
| `before`      | JSON NULLABLE    | État avant modification                                                                           |
| `after`       | JSON NULLABLE    | État après modification                                                                           |
| `ip_address`  | VARCHAR(45)      | Adresse IP de l'auteur                                                                            |
| `user_agent`  | TEXT             | Navigateur                                                                                        |
| `created_at`  | TIMESTAMP        | Date de l'action                                                                                  |

> Utilisé via le helper global `audit('action', 'module', $id, $avant, $apres)`.
>
> **Note :** les prévisualisations dry-run ne génèrent **aucune entrée** dans `audit_logs` — la transaction étant rollbackée, l'appel à `audit()` à l'intérieur ne se produit pas (il n'y en a pas dans le chemin dry-run).
>
> chaque création, acceptation, refus, expiration ou annulation d'un échange de créneau (`plan_echanges`) génère une entrée `module = 'echanges'`. Les actions système (expiration via la commande planifiée) ont `user_id = NULL`, comme pour les webhooks.

---

### `sessions`

Sessions utilisateurs (driver `database` de Laravel).

| Colonne         | Type         | Description                            |
| --------------- | ------------ | -------------------------------------- |
| `id`            | VARCHAR PK   | Identifiant de session                 |
| `user_id`       | INT NULLABLE | Référence vers `ref_personnes`         |
| `ip_address`    | VARCHAR(45)  | Adresse IP                             |
| `user_agent`    | TEXT         | Navigateur                             |
| `payload`       | LONGTEXT     | Données de session sérialisées         |
| `last_activity` | INT          | Timestamp Unix de la dernière activité |

> **Données de session utilisées par les fonctionnalités existantes :**
>
> | Clé session               | Contenu                                                     | Durée de vie                                            |
> | ------------------------- | ----------------------------------------------------------- | ------------------------------------------------------- |
> | `pending_generation`      | `date_debut`, `semaines`, `semaines_affectees`, `nb_total`  | Jusqu'à confirmation, annulation ou nouvelle soumission |
> | `last_generated_creneaux` | Liste des créneaux générés pour le rollback post-génération | Jusqu'à dismiss ou rollback explicite                   |
>
> Les échanges de créneaux (`plan_echanges`) et la synchronisation Google Calendar des événements **n'utilisent aucune donnée de session** — tout leur état vit en base de données.

---

### `password_reset_tokens`

Tokens de réinitialisation de mot de passe (standard Laravel, broker `personnes`).

| Colonne      | Type       | Description                            |
| ------------ | ---------- | -------------------------------------- |
| `email`      | VARCHAR PK | Email de la personne                   |
| `token`      | VARCHAR    | Token hashé                            |
| `created_at` | TIMESTAMP  | Date de création — expire après 60 min |

---

### `jobs` / `job_batches` / `failed_jobs`

Tables standard Laravel pour la gestion des queues.

> Avec `QUEUE_CONNECTION=sync`, ces tables ne sont **pas utilisées** mais restent présentes dans le schéma (créées par la migration Laravel par défaut). Elles peuvent être ignorées.
>
> En production (`QUEUE_CONNECTION=database`), ces tables portent désormais aussi les jobs `EnvoyerWebhookMake` de type `evenement` (synchronisation calendrier) et les notifications `App\Notifications\Echanges\*` (toutes `ShouldQueue`).

---

### `cache` / `cache_locks`

Tables standard Laravel pour le driver de cache `database`.
