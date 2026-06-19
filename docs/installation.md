# Guide d'installation — AMANA Planning

## Table des matières

1. [Prérequis généraux](#prérequis-généraux)
2. [Installation en environnement local (Ubuntu 24.04)](#installation-en-environnement-local-ubuntu-2404)
3. [Déploiement en production (IONOS)](#déploiement-en-production-ionos)
4. [Première connexion et changement de mot de passe](#première-connexion-et-changement-de-mot-de-passe)
5. [Référence des routes principales](#référence-des-routes-principales)
6. [Résolution des problèmes courants](#résolution-des-problèmes-courants)

---

## Prérequis généraux

| Composant       | Version minimale |
| --------------- | ---------------- |
| PHP             | 8.4+             |
| MySQL / MariaDB | 8.0+ / 10.4+     |
| Nginx           | 1.24+            |
| Composer        | 2.x              |
| Git             | 2.x              |

---

## Installation en environnement local (Ubuntu 24.04)

Cette section couvre une installation complète sur Ubuntu 24.04 LTS avec PHP 8.4, Nginx et MariaDB. Toutes les commandes sont à exécuter en tant qu'utilisateur normal — `sudo` est utilisé explicitement là où les droits root sont nécessaires.

### 1. Mettre à jour le système

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Installer PHP 8.4

Ubuntu 24.04 ne fournit pas PHP 8.4 dans ses dépôts officiels. On utilise le PPA de Ondřej Surý, la référence standard pour les versions PHP récentes sur Debian/Ubuntu.

```bash
# Ajouter le PPA
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Installer PHP 8.4 et les extensions requises par Laravel 13
sudo apt install -y \
    php8.4 \
    php8.4-fpm \
    php8.4-cli \
    php8.4-mysql \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-tokenizer \
    php8.4-ctype \
    php8.4-fileinfo \
    php8.4-dom \
    php8.4-intl \
    php8.4-gd
```

Vérifier l'installation :

```bash
php8.4 -v
# PHP 8.4.x (cli)
```

Définir PHP 8.4 comme version par défaut :

```bash
sudo update-alternatives --set php /usr/bin/php8.4
php -v
# PHP 8.4.x (cli)
```

### 3. Installer Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

Vérifier :

```bash
nginx -v
# nginx version: nginx/1.24.x
curl -s -o /dev/null -w "%{http_code}" http://localhost
# 200
```

### 4. Installer MariaDB

```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable mariadb
sudo systemctl start mariadb
```

Sécuriser l'installation (définir le mot de passe root, supprimer les utilisateurs anonymes) :

```bash
sudo mariadb-secure-installation
# Répondre aux questions :
#   Switch to unix_socket authentication  → N
#   Change the root password              → Y  (définir un mot de passe fort)
#   Remove anonymous users                → Y
#   Disallow root login remotely          → Y
#   Remove test database                  → Y
#   Reload privilege tables               → Y
```

### 5. Créer la base de données

```bash
sudo mariadb -u root -p
```

Dans le prompt MariaDB :

```sql
-- Créer la base de données
CREATE DATABASE amana
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié (remplacer 'motdepasse' par un vrai mot de passe)
CREATE USER 'amana_user'@'localhost' IDENTIFIED BY 'motdepasse';

-- Accorder tous les droits sur la base
GRANT ALL PRIVILEGES ON amana.* TO 'amana_user'@'localhost';

-- Appliquer les changements
FLUSH PRIVILEGES;

-- Vérifier
SHOW DATABASES;
EXIT;
```

### 6. Installer Composer

```bash
# Télécharger et installer Composer globalement
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

composer -V
# Composer version 2.x.x
```

### 7. Installer Git

```bash
sudo apt install -y git
git --version
```

Configurer votre identité Git si ce n'est pas encore fait :

```bash
git config --global user.name "Votre Nom"
git config --global user.email "votre@email.fr"
```

### 8. Cloner le projet

```bash
# Choisir un répertoire de travail — /var/www est standard pour les projets web
sudo mkdir -p /var/www/amana-planning
sudo chown $USER:$USER /var/www/amana-planning

git clone https://github.com/votre-organisation/amana-planning.git /var/www/amana-planning
cd /var/www/amana-planning
```

### 9. Installer les dépendances PHP

```bash
composer install
```

### 10. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditer `.env` :

```dotenv
APP_NAME="AMANA Planning"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amana
DB_USERNAME=amana_user
DB_PASSWORD=motdepasse

# En local, file est plus simple — pas besoin de la table sessions
SESSION_DRIVER=file
SESSION_LIFETIME=120

QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=log

MAKE_WEBHOOK_URL=
```

> **`SESSION_DRIVER=file`** : suffisant en local. La table `sessions` n'est pas nécessaire. En production (IONOS), utiliser `database` à la place.
>
> **`QUEUE_CONNECTION=sync`** : les emails sont traités directement sans worker. Avec `MAIL_MAILER=log`, ils s'écrivent dans `storage/logs/laravel.log` au lieu d'être envoyés.
>
> **`HEURE_COURS` est obsolète** et ignorée. L'heure du cours est gérée exclusivement via **Paramètres → Heure du cours** dans l'interface, stockée dans `ref_settings`.

### 11. Permissions des dossiers

```bash
sudo chown -R $USER:www-data /var/www/amana-planning
sudo chmod -R 755 /var/www/amana-planning
sudo chmod -R 775 /var/www/amana-planning/storage
sudo chmod -R 775 /var/www/amana-planning/bootstrap/cache
```

### 12. Exécuter les migrations et les seeders

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

### 13. Créer le lien de stockage public

```bash
php artisan storage:link
```

### 14. Configurer Nginx

Créer le fichier de configuration du virtual host :

```bash
sudo nano /etc/nginx/sites-available/amana-planning
```

Coller la configuration suivante :

```nginx
server {
    listen 80;
    server_name localhost;

    root /var/www/amana-planning/public;
    index index.php index.html;

    # Journaux
    access_log /var/log/nginx/amana-planning.access.log;
    error_log  /var/log/nginx/amana-planning.error.log;

    # Toutes les requêtes passent par index.php (routing Laravel)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Traitement PHP via PHP-FPM 8.4
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Refuser l'accès aux fichiers cachés (.env, .git, etc.)
    location ~ /\. {
        deny all;
    }

    # Cache navigateur pour les assets statiques
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

Activer le site et désactiver le site par défaut :

```bash
sudo ln -s /etc/nginx/sites-available/amana-planning /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Vérifier la syntaxe
sudo nginx -t
# nginx: configuration file /etc/nginx/nginx.conf test is successful

# Recharger Nginx
sudo systemctl reload nginx
```

### 15. Configurer PHP-FPM

Vérifier que PHP-FPM 8.4 tourne :

```bash
sudo systemctl status php8.4-fpm
# Active: active (running)
```

Ajuster si nécessaire la configuration PHP pour le développement :

```bash
sudo nano /etc/php/8.4/fpm/php.ini
```

Valeurs recommandées en local :

```ini
display_errors = On
error_reporting = E_ALL
max_execution_time = 120
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 20M
```

Redémarrer PHP-FPM après modification :

```bash
sudo systemctl restart php8.4-fpm
```

### 16. Aucune compilation front-end nécessaire

Le CSS et le JS de l'application sont des fichiers statiques sous `public/css/*.css` et `public/js/*.js`, chargés directement par le navigateur via `asset()`. Il n'y a ni npm, ni Vite, ni étape de build : modifier un fichier puis recharger la page suffit.

### 17. Vérifier l'installation

Ouvrir <http://localhost> dans le navigateur. La page de connexion AMANA Planning doit s'afficher.

Si quelque chose ne fonctionne pas :

```bash
# Logs Nginx
sudo tail -f /var/log/nginx/amana-planning.error.log

# Logs Laravel
tail -f /var/www/amana-planning/storage/logs/laravel.log

# Logs PHP-FPM
sudo tail -f /var/log/php8.4-fpm.log
```

> Avec `QUEUE_CONNECTION=sync`, **aucun worker de queue n'est nécessaire.** Les emails sont traités directement lors de chaque action.

---

## Déploiement en production (IONOS)

### 1. Accès SSH

```bash
ssh votre-utilisateur@votre-serveur.ionos.fr
```

### 2. Vérifier les prérequis serveur

```bash
php -v          # 8.4+
mysql --version
composer -V
git --version
```

Si PHP 8.4 n'est pas la version par défaut, l'activer dans le panneau IONOS : **Hébergement Web > Configuration PHP**.

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

# En production, utiliser database pour plus de fiabilité
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=votredomaine.ionos.fr

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

> **`SESSION_DRIVER=database`** : plus fiable sur hébergement partagé IONOS où les permissions du dossier `storage/framework/sessions` peuvent être instables. Nécessite que la table `sessions` existe en base (créée par les migrations).
>
> **`QUEUE_CONNECTION=sync`** : aucun worker ni cron job nécessaire. Les emails SMTP sont envoyés directement lors de chaque action. Le délai ajouté est négligeable (< 2 secondes).
>
> Les identifiants de base de données sont disponibles dans le panneau IONOS > **Bases de données**.

### 6. Exécuter les migrations et les seeders

```bash
php artisan migrate --force
php artisan db:seed --force
```

La migration `2026_06_18_000001_create_sessions_table.php` crée la table `sessions` requise par `SESSION_DRIVER=database`.

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

Comme en local, le CSS et le JS sont des fichiers statiques sous `public/css/` et `public/js/` — rien à compiler. Ils sont inclus dans le dépôt Git.

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

echo "==> Redémarrage du worker queue (si applicable)..."
php artisan queue:restart 2>/dev/null || true

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
4. Suivre le lien reçu par email (ou dans `storage/logs/laravel.log` en local avec `MAIL_MAILER=log`)
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
sudo chmod -R 775 storage bootstrap/cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Nginx retourne 502 Bad Gateway

PHP-FPM n'est pas démarré ou le socket est incorrect.

```bash
# Vérifier que PHP-FPM tourne
sudo systemctl status php8.4-fpm

# Redémarrer si nécessaire
sudo systemctl restart php8.4-fpm

# Vérifier que le socket existe
ls -la /run/php/php8.4-fpm.sock
```

Si le socket n'existe pas, vérifier la configuration du pool PHP-FPM :

```bash
sudo cat /etc/php/8.4/fpm/pool.d/www.conf | grep listen
# listen = /run/php/php8.4-fpm.sock
```

### Erreur « Table sessions doesn't exist »

La table `sessions` est requise quand `SESSION_DRIVER=database`. Lancer les migrations :

```bash
php artisan migrate --force
```

Si la migration est déjà marquée comme exécutée mais que la table est absente :

```bash
# Vérifier le statut
php artisan migrate:status

# Recréer manuellement si nécessaire
php artisan migrate --path=database/migrations/2026_06_18_000001_create_sessions_table.php
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

### Problème de session (déconnexion intempestive) en production

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=votredomaine.fr
SESSION_SECURE_COOKIE=true
```

```bash
# S'assurer que la table sessions existe
php artisan migrate --force
```

### Nginx retourne 403 Forbidden

Le document root ou les permissions sont incorrects.

```bash
# Vérifier que le document root pointe vers public/
grep -r "root" /etc/nginx/sites-enabled/amana-planning

# Vérifier les permissions
ls -la /var/www/amana-planning/public/index.php

# Corriger si nécessaire
sudo chown -R $USER:www-data /var/www/amana-planning
sudo chmod -R 755 /var/www/amana-planning/public
```

### La prévisualisation (Aperçu) est lente

Le dry-run exécute l'algorithme complet de génération sans persister. C'est normal. Pour des plannings longs (> 20 semaines), le temps de réponse peut atteindre quelques secondes. Si le serveur retourne un timeout, augmenter `max_execution_time` :

```bash
# Local : éditer /etc/php/8.4/fpm/php.ini
sudo nano /etc/php/8.4/fpm/php.ini
# max_execution_time = 120

sudo systemctl restart php8.4-fpm
```

### L'avertissement de chevauchement persiste après annulation

La session `pending_generation` est normalement effacée par le bouton **Annuler** (route `planning.overlap.cancel`). Si elle persiste :

```bash
# Vider toutes les sessions en base (production)
php artisan tinker
DB::table('sessions')->truncate();
```

### « Mon planning » ne montre aucun créneau

Vérifier que la personne connectée est bien assignée dans `plan_creneaux_taches` avec son `id_personne`. Si le planning a été généré avant que la personne soit ajoutée au système, ses créneaux n'existeront pas — régénérer ou assigner manuellement via la vue planning principale.

### Vérifier la version PHP utilisée par Nginx / CLI

```bash
# Version CLI
php -v

# Version utilisée par PHP-FPM (celle que Nginx appelle)
sudo php-fpm8.4 -v

# S'assurer que les deux sont bien 8.4
php artisan about | grep "PHP Version"
```
