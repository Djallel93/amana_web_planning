# Développement local avec Docker

Une seule commande pour lancer PHP 8.4, Node 22, MySQL 8.4 et Mailpit, à
l'identique sous Windows (WSL2) et Linux. **Usage local uniquement** : la
CI/CD (`deploy.yaml`, `deploy-preprod.yaml`) n'utilise pas Docker. Ce dépôt
reprend la pile de `amana_web_familles` (voir son `docs/DOCKER.md` pour
l'installation de Docker Desktop / Docker Engine).

## 1. Services et ports

| Service   | Rôle                                                | Port hôte                                 |
| --------- | --------------------------------------------------- | ----------------------------------------- |
| `app`     | `composer install`, migrations, `php artisan serve` | <http://localhost:**8001>**               |
| `vite`    | `npm install`, serveur Vite (HMR)                   | **5174**                                  |
| `mysql`   | bases `amana_planning` + `amana_commun` (+ `_test`) | **3307**                                  |
| `mailpit` | capture les e-mails sortants                        | UI <http://localhost:**8026>**, SMTP 1026 |
| `queue`   | `queue:work`, optionnel (`make worker`)             | —                                         |

Les ports sont **décalés** par rapport à ceux de familles (8000 / 5173 / 3306 /
8025) pour pouvoir lancer les deux piles en même temps. Pour les changer :
`APP_PORT`, `VITE_PORT`, `MYSQL_PORT`, `MAILPIT_UI_PORT`, `MAILPIT_SMTP_PORT`
dans `.env` (et `APP_URL` en cohérence).

## 2. Disposition attendue des dépôts

```txt
amana/
├── amana_web_planning/   ← ce dépôt (docker-compose.yml)
├── amana_shared/
└── amana_shared_ui/
```

Le dossier parent `amana/` est monté tel quel dans les conteneurs : les chemins
relatifs de `composer.local.json` (`../amana_shared`) et de `npm link`
fonctionnent comme décrit dans `docs/local-development.md`.

## 3. Premier démarrage

```bash
cd amana/amana_web_planning
cp .env.docker.example .env
# Dépôts privés uniquement : export AMANA_REPOS_PAT=github_pat_…
make up          # build + démarrage (le 1er démarrage installe vendor/ et node_modules/)
make logs        # attendre « ==> Ready » puis « starting Vite dev server »
make fresh       # base vierge + données de référence + comptes de test
```

Application : <http://localhost:8001> — e-mails : <http://localhost:8026>.

## 4. Comptes de test (seedés par `make fresh`)

| E-mail                             | Rôle planning                 | Mot de passe   |
| ---------------------------------- | ----------------------------- | -------------- |
| `admin@amana.fr`                   | admin                         | `changeme123!` |
| `test1@amana.fr … test10@amana.fr` | membre                        | `password`     |
| `e2e-admin@amana.test`             | admin                         | `e2e-password` |
| `e2e-gestionnaire@amana.test`      | gestionnaire                  | `e2e-password` |
| `e2e-membre@amana.test`            | membre                        | `e2e-password` |
| `e2e-benevole@amana.test`          | bénévole                      | `e2e-password` |
| `e2e-attente@amana.test`           | aucun (statut « En attente ») | `e2e-password` |

Les comptes `e2e-*` (seeder `database/seeders/Docker/E2eRolesSeeder.php`) servent
aux tests Playwright. Le seeder **refuse de s'exécuter hors `APP_ENV=local|testing`**.

## 5. Commandes utiles

| Commande               | Effet                                                                               |
| ---------------------- | ----------------------------------------------------------------------------------- |
| `make up` / `down`     | démarrer / arrêter la pile                                                          |
| `make shell`           | shell dans le conteneur `app`                                                       |
| `make artisan migrate` | `php artisan …`                                                                     |
| `make test`            | PHPUnit (bases `*_test`, voir `make test-db-init` si le volume MySQL existait déjà) |
| `make fresh`           | `migrate:fresh --seed` + comptes de test                                            |
| `make worker`          | démarre `queue:work` (régler `QUEUE_CONNECTION=database`)                           |

## 6. Dépannage

- **Port déjà utilisé** : changer le port correspondant dans `.env` (§1).
- **Vite ne se connecte pas / pas de rechargement** : `VITE_PORT` doit être
  identique dans `.env` et dans l'URL du fichier `public/hot` ; `docker compose
  restart vite`.
- **Fichiers `storage/` appartenant à root** : l'entrypoint les rend à
  `HOST_UID`/`HOST_GID` ; exporter `UID`/`GID` si votre utilisateur n'est pas 1000.
- **Repartir de zéro** (supprime toutes les données locales) :
  `docker compose down -v && make up && make fresh`.
- **Calendriers Google** : non configurés en local (`GOOGLE_SERVICE_ACCOUNT_JSON_BASE64`
  vide) ; la synchronisation est simplement inactive.
