# AMANA Planning — Application Laravel

Migration du système Google Apps Script → PHP Laravel 11.

---

## Installation rapide (développement local)

```bash
# 1. Cloner le projet
git clone https://github.com/toncompte/amana-planning.git
cd amana-planning

# 2. Installer les dépendances PHP
composer install

# 3. Copier et configurer .env
cp .env.example .env
# Éditer .env : DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 4. Générer la clé
php artisan key:generate

# 5. Créer la base de données
# (voir PARTIE1_INSTALLATION.md)
# Puis :
php artisan migrate

# 6. Insérer les données initiales (tâches, rôles, admin)
php artisan db:seed

# 7. Lancer le serveur
php artisan serve
```

Ouvre http://localhost:8000
- Email : `admin@amana.fr`
- Mot de passe : `changeme123!`

---

## Structure des fichiers générés

```
app/
├── Helpers/
│   ├── AuditHelper.php          # Classe de journalisation
│   └── helpers.php              # Fonction globale audit()
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── PlanningController.php
│   │   ├── PersonnesController.php
│   │   ├── RestrictionsController.php
│   │   ├── AbsencesController.php
│   │   └── EvenementsController.php
│   ├── Middleware/
│   │   └── EnsureAuthenticated.php
│   └── Requests/
│       ├── Absences/StoreAbsenceRequest.php
│       ├── Evenements/{Store,Update}EvenementRequest.php
│       ├── Personnes/{Store,Update}PersonneRequest.php
│       └── Planning/PlanningGenerateRequest.php
├── Models/
│   ├── AuditLog.php
│   ├── Absence.php
│   ├── Creneau.php
│   ├── CreneauTache.php
│   ├── Evenement.php
│   ├── Personne.php
│   ├── Restriction.php
│   ├── Role.php
│   ├── Tache.php
│   └── Vehicule.php
└── Services/
    ├── DataLoader.php           # Chargement données + contexte
    ├── RotationEngine.php       # Algorithme de rotation (= RotationEngine.js)
    ├── SchedulerMain.php        # Orchestration génération (= SchedulerMain.js)
    └── Statistics.php           # Calcul métriques (= Statistics.js)

database/migrations/
├── 2024_01_01_000000_create_audit_logs_table.php
├── 2024_01_01_000001_create_base_tables.php       # ref_vehicules, ref_roles, ref_taches, ref_personnes
├── 2024_01_01_000002_create_planning_tables.php   # plan_*, ref_evenements
└── 2024_01_01_000003_create_geo_benevoles_tables.php

resources/views/
├── layouts/app.blade.php        # Navigation + styles globaux
├── auth/login.blade.php
├── planning/{index,generate}.blade.php
├── personnes/{index,form}.blade.php
├── restrictions/index.blade.php
├── absences/index.blade.php
├── evenements/{index,form}.blade.php
└── statistics/index.blade.php

routes/web.php                   # Toutes les routes nommées
bootstrap/app.php                # Enregistrement middleware auth
```

---

## Workflow de génération du planning

1. **DataLoader** charge depuis MySQL :
   - Personnes actives avec leurs restrictions
   - Absences actuelles
   - Événements organisationnels
   - Historique des assignations (pour les compteurs)

2. **RotationEngine** assigne les 4 tâches pour chaque créneau :
   - `amana_food` : cycle global, la personne avec le moins d'assignations passe en premier
   - `entree`, `mektaba`, `salle` : score = (total × 10) - (repos × 1) + (répétition × multiplicateur)

3. **SchedulerMain** orchestre la génération et persiste en base

---

## Sécurité

Toutes les règles sont respectées :
- `declare(strict_types=1)` dans chaque fichier
- Requêtes via Eloquent ORM uniquement (zéro concaténation SQL)
- Validation via Form Requests (`StorePersonneRequest`, etc.)
- Protection CSRF via `@csrf` dans tous les formulaires
- Authentification via middleware `EnsureAuthenticated`
- Échappement automatique Blade (`{{ $var }}`)
- Toutes les actions loguées via `audit()`
- Messages flash via `session()->flash()`
- Données sensibles dans `.env` uniquement

---

## Documentation

- `docs/PARTIE1_INSTALLATION.md` — Installation environnement local
- `docs/PARTIE10_DEPLOIEMENT.md` — Déploiement sur IONOS
