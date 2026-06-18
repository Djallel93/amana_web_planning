# Guide d'installation — AMANA Planning

## Table des matières

1. [Prérequis généraux](#prérequis-généraux)
2. [Installation en environnement local (XAMPP)](#installation-en-environnement-local-xampp)
3. [Déploiement en production (IONOS)](#déploiement-en-production-ionos)
4. [Première connexion et changement de mot de passe](#première-connexion-et-changement-de-mot-de-passe)
5. [Résolution des problèmes courants](#résolution-des-problèmes-courants)

---

## Prérequis généraux

| Composant       | Version minimale |
| --------------- | ---------------- |
| PHP             | 8.2+             |
| MySQL / MariaDB | 8.0+ / 10.4+     |
| Composer        | 2.x              |
| Git             | 2.x              |

---

## Installation en environnement local (XAMPP)

### 1. Installer XAMPP

Télécharger XAMPP depuis <https://www.apachefriends.org> et installer la version incluant PHP 8.2+.

Démarrer les modules **Apache** et **MySQL** depuis le panneau de contrôle XAMPP.

### 2. Vérifier les dépendances système

#### PHP

```bash
php -v
# Doit afficher PHP 8.2.x ou supérieur
```

Sur Windows, ajouter `C:\xampp\php` aux variables d'environnement si PHP n'est pas dans le PATH.

#### Composer

```bash
composer -V

# Si absent — Linux/macOS
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Windows : https://getcomposer.org/Composer-Setup.exe
```

#### Git

```bash
git --version

# Ubuntu/Debian
sudo apt-get install git

# macOS
brew install git

# Windows : https://git-scm.com/download/win
```

### 3. Cloner le projet

```bash
cd /opt/lampp/htdocs          # Linux
# cd C:/xampp/htdocs          # Windows

git clone https://github.com/votre-organisation/amana-planning.git
cd amana-planning
```

### 4. Installer les dépendances PHP

```bash
composer install
```

### 5. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Ouvrir `.env` et adapter :

```dotenv
APP_NAME="AMANA Planning"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/amana-planning/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amana_planning
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=log

MAKE_WEBHOOK_URL=
```

> **`QUEUE_CONNECTION=sync`** : les emails sont envoyés directement sans worker. En local, `MAIL_MAILER=log` les écrit dans `storage/logs/laravel.log` au lieu de les envoyer.
>
> **`HEURE_COURS` est obsolète** et ignorée. L'heure du cours est gérée exclusivement via **Paramètres → Heure du cours** dans l'interface, stockée dans `ref_settings`.

### 6. Créer la base de données

Via phpMyAdmin (<http://localhost/phpmyadmin>) :

1. « Nouvelle base de données » > Nom : `amana_planning` > Interclassement : `utf8mb4_unicode_ci`

Ou en ligne de commande :

```bash
mysql -u root -e "CREATE DATABASE amana_planning CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 7. Exécuter les migrations et les seeders

```bash
php artisan migrate --force
php artisan db:seed --force
```

Crée toutes les tables et le compte administrateur par défaut :

| Champ        | Valeur           |
| ------------ | ---------------- |
| Email        | `admin@amana.fr` |
| Mot de passe | `changeme123!`   |

> ⚠️ **Changer ce mot de passe immédiatement après la première connexion.**

### 8. Créer le lien de stockage public

```bash
php artisan storage:link
```

### 9. Aucune compilation front-end nécessaire

Le CSS et le JS de l'application sont des fichiers statiques sous `public/css/*.css` et `public/js/*.js`, chargés directement par le navigateur via `asset()`. Il n'y a ni npm, ni Vite, ni étape de build : modifier un fichier puis recharger la page suffit pour voir le changement.

### 10. Lancer le serveur

#### Option A — serveur intégré PHP (recommandé)

```bash
php artisan serve
# Disponible sur http://127.0.0.1:8000
```

#### Option B — via Apache XAMPP

Accéder à <http://localhost/amana-planning/public>

Si Apache retourne une erreur 500, vérifier que `mod_rewrite` est activé et que `AllowOverride All` est défini dans la config Apache.

> Avec `QUEUE_CONNECTION=sync`, **aucun worker de queue n'est nécessaire.** Les emails partent directement lors de chaque action.

---

## Déploiement en production (IONOS)

### 1. Accès SSH

```bash
ssh votre-utilisateur@votre-serveur.ionos.fr
```

### 2. Vérifier les prérequis serveur

```bash
php -v          # 8.2+
mysql --version
composer -V
git --version
```

Si PHP 8.2 n'est pas la version par défaut, l'activer dans le panneau IONOS : **Hébergement Web > Configuration PHP**.

### 3. Cloner le projet

```bash
cd /var/www/vhosts/votredomaine.fr/httpdocs

git clone https://github.com/votre-organisation/amana-planning.git .
```

### 4. Installer les dépendances PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 5. Configurer l'environnement de production

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :

```dotenv
APP_NAME="AMANA Planning"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votredomaine.ionos.fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nom_de_votre_bdd
DB_USERNAME=utilisateur_bdd
DB_PASSWORD=mot_de_passe_bdd

SESSION_DRIVER=database
QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.ionos.fr
MAIL_PORT=587
MAIL_USERNAME=votre@email.fr
MAIL_PASSWORD=mot_de_passe_smtp
MAIL_FROM_ADDRESS=votre@email.fr
MAIL_FROM_NAME="AMANA Planning"
MAIL_SCHEME=tls

MAKE_WEBHOOK_URL=https://hook.make.com/votre-webhook
```

> **`QUEUE_CONNECTION=sync`** : aucun worker ni cron job nécessaire. Les emails SMTP sont envoyés directement lors de chaque action. Le délai ajouté est négligeable (< 2 secondes).
>
> Les identifiants de base de données sont disponibles dans le panneau IONOS > **Bases de données**.

### 6. Exécuter les migrations et les seeders

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 7. Créer le lien de stockage public

```bash
php artisan storage:link
```

### 8. Mettre en cache la configuration, les routes et les vues

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 9. Aucune compilation front-end nécessaire

Comme en local, le CSS et le JS sont des fichiers statiques sous `public/css/` et `public/js/` — rien à compiler, rien à transférer séparément. Ils sont déjà inclus dans le dépôt cloné à l'étape 3.

### 10. Configurer le document root

Sur IONOS, le document root doit pointer vers le sous-dossier `public/` du projet.

Via le panneau IONOS : **Hébergement Web > votre domaine > Répertoire Web** → définir `/httpdocs/public`.

Ou créer un `.htaccess` à la racine du `httpdocs` :

```apache
RewriteEngine On
RewriteRule ^(.*)$ public/$1 [L]
```

### 11. Permissions des dossiers

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
# Adapter l'utilisateur selon la config IONOS
```

### 12. Script de déploiement (mises à jour)

Créer `deploy.sh` à la racine du projet :

```bash
#!/bin/bash
set -e

echo "==> Mise à jour du code..."
git pull origin main

echo "==> Installation des dépendances..."
composer install --no-dev --optimize-autoloader

echo "==> Migrations..."
php artisan migrate --force

echo "==> Mise en cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Terminé."
```

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## Première connexion et changement de mot de passe

### Connexion initiale

1. Ouvrir l'application dans le navigateur
2. Se connecter :
    - **Email :** `admin@amana.fr`
    - **Mot de passe :** `changeme123!`

### Changer le mot de passe

#### Méthode 1 — Via l'interface (recommandée)

1. Se déconnecter
2. Aller sur `/mot-de-passe-oublie`
3. Saisir `admin@amana.fr` et valider
4. Suivre le lien reçu par email (ou dans `storage/logs/laravel.log` en local)
5. Définir un nouveau mot de passe

#### Méthode 2 — Via Artisan (SSH)

```bash
php artisan amana:reset-admin --email=admin@amana.fr
# Mot de passe sécurisé généré et affiché une seule fois

# Avec un mot de passe personnalisé
php artisan amana:reset-admin --email=admin@amana.fr --password=MonNouveauMotDePasse123!
```

### Créer son propre compte administrateur

1. **Administration > Personnes > + Ajouter**
2. Remplir nom, prénom, email — rôle **Administrateur** — statut **Validé**
3. Enregistrer
4. Aller sur `/mot-de-passe-oublie` avec cet email pour recevoir le lien de création de mot de passe

---

## Référence des routes principales

| Méthode | URL                          | Nom                         | Accès              | Description                                           |
| ------- | ---------------------------- | --------------------------- | ------------------ | ----------------------------------------------------- |
| GET     | `/`                          | —                           | Public             | Redirige vers `/planning`                             |
| GET     | `/login`                     | `login`                     | Public             | Formulaire de connexion                               |
| GET     | `/inscription`               | `inscription`               | Public             | Formulaire d'inscription publique                     |
| GET     | `/planning`                  | `planning.index`            | Connecté           | Vue principale du planning (grille semaines)          |
| GET     | `/mon-planning`              | `mon-planning`              | Connecté           | Vue personnelle — créneaux du membre connecté         |
| GET     | `/planning/stats`            | `planning.statistics`       | Connecté           | Tableau de bord statistiques                          |
| GET     | `/planning/export`           | `planning.export.form`      | Connecté           | Formulaire export PDF                                 |
| POST    | `/planning/export/pdf`       | `planning.export.pdf`       | Connecté           | Génération et téléchargement du PDF                   |
| GET     | `/planning/generer`          | `planning.generate.form`    | Gestionnaire+Admin | Formulaire de génération                              |
| POST    | `/planning/generer`          | `planning.generate`         | Gestionnaire+Admin | Génération effective (avec contrôle de chevauchement) |
| POST    | `/planning/generer/apercu`   | `planning.preview`          | Gestionnaire+Admin | Prévisualisation dry-run sans persistance             |
| POST    | `/planning/overlap/cancel`   | `planning.overlap.cancel`   | Gestionnaire+Admin | Annule la session de confirmation de chevauchement    |
| POST    | `/planning/rollback`         | `planning.rollback`         | Gestionnaire+Admin | Rollback total ou partiel post-génération             |
| POST    | `/planning/rollback/dismiss` | `planning.rollback.dismiss` | Gestionnaire+Admin | Ferme la session de rollback sans supprimer           |
| GET     | `/absences`                  | `absences.index`            | Connecté           | Liste des absences                                    |
| GET     | `/restrictions`              | `restrictions.index`        | Connecté           | Grille des disponibilités                             |
| GET     | `/evenements`                | `evenements.index`          | Connecté           | Liste des événements                                  |
| GET     | `/parametres`                | `settings.index`            | Gestionnaire+Admin | Page de paramètres                                    |
| GET     | `/personnes`                 | `personnes.index`           | Admin              | Liste des membres                                     |
| GET     | `/admin/candidatures`        | `admin.candidatures.index`  | Admin              | Tableau de bord des candidatures                      |

---

## Résolution des problèmes courants

### Erreur 500 au premier déploiement

```bash
tail -f storage/logs/laravel.log
chmod -R 775 storage bootstrap/cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Les emails ne partent pas

Vérifier la config SMTP dans `.env`. Avec `QUEUE_CONNECTION=sync`, les erreurs d'envoi apparaissent directement dans les logs :

```bash
tail -f storage/logs/laravel.log
```

Tester la connexion SMTP :

```bash
php artisan tinker
Mail::raw('Test', fn($m) => $m->to('votre@email.fr')->subject('Test AMANA'));
```

### Erreur de migration « Table already exists »

```bash
php artisan migrate:status
php artisan migrate --force
```

### Page blanche après config:cache

```bash
php artisan config:clear
php artisan config:cache
```

### Problème de session (déconnexion intempestive)

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=votredomaine.fr
SESSION_SECURE_COOKIE=true
```

```bash
php artisan migrate --force   # s'assurer que la table sessions existe
```

### La prévisualisation (Aperçu) est lente

Le dry-run exécute l'algorithme complet de génération deux fois (une pour la preview, une pour la génération réelle après confirmation). C'est normal. Pour des plannings longs (> 20 semaines), le temps de réponse peut atteindre quelques secondes. Si le serveur retourne un timeout, augmenter `max_execution_time` dans `php.ini` ou réduire le nombre de semaines dans la prévisualisation.

### L'avertissement de chevauchement persiste après annulation

La session `pending_generation` est normalement effacée par le bouton **Annuler** (route `planning.overlap.cancel`). Si elle persiste, la vider manuellement :

```bash
php artisan tinker
session()->forget('pending_generation');
# ou vider toutes les sessions
php artisan session:flush   # si disponible
# sinon, vider la table sessions en base
```

### « Mon planning » ne montre aucun créneau

Vérifier que la personne connectée est bien assignée dans `plan_creneaux_taches` avec son `id_personne`. Si le planning a été généré avant que la personne soit ajoutée au système, ses créneaux n'existeront pas — régénérer ou assigner manuellement via la vue planning principale.
