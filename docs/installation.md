# Guide d'installation — AMANA Planning

## Table des matières

1. [Prérequis généraux](#prérequis-généraux)
2. [Installation en environnement local (Ubuntu 24.04 / Pop!\_OS)](#installation-en-environnement-local-ubuntu-2404--popos)
3. [Déploiement en production (IONOS)](#déploiement-en-production-ionos)
4. [Première connexion et changement de mot de passe](#première-connexion-et-changement-de-mot-de-passe)
5. [Référence des routes principales](#référence-des-routes-principales)
6. [Résolution des problèmes courants](#résolution-des-problèmes-courants)

---

## Prérequis généraux

| Composant       | Version minimale | Notes                                      |
| --------------- | ---------------- | ------------------------------------------ |
| PHP             | 8.2+             | 8.4 recommandé                             |
| MySQL / MariaDB | 8.0+ / 10.4+     |                                            |
| Nginx           | 1.24+            |                                            |
| Composer        | 2.x              |                                            |
| Node.js         | 20.19+ ou 22.x   | Requis en développement pour le build Vite |
| npm             | Inclus avec Node |                                            |
| Git             | 2.x              |                                            |

> **En production (IONOS)**, Node.js n'est **pas** requis. Le dossier `public/build/` (CSS compilé) est commité dans git et déployé directement.

---

## Installation en environnement local (Ubuntu 24.04 / Pop!\_OS)

### 1. Mettre à jour le système

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Installer PHP 8.4

Ubuntu 24.04 ne fournit pas PHP 8.4 dans ses dépôts officiels. On utilise le PPA d'Ondřej Surý.

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install -y \
    php8.4 php8.4-fpm php8.4-cli php8.4-mysql \
    php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
    php8.4-bcmath php8.4-tokenizer php8.4-ctype \
    php8.4-fileinfo php8.4-dom php8.4-intl php8.4-gd

# Définir PHP 8.4 comme version par défaut
sudo update-alternatives --set php /usr/bin/php8.4
php -v
```

### 3. Installer Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx && sudo systemctl start nginx
```

### 4. Installer MariaDB

```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl enable mariadb && sudo systemctl start mariadb
sudo mariadb-secure-installation
```

### 5. Créer la base de données

```bash
sudo mariadb -u root -p
```

```sql
CREATE DATABASE amana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'amana_user'@'localhost' IDENTIFIED BY 'motdepasse';
GRANT ALL PRIVILEGES ON amana.* TO 'amana_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 6. Installer Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
composer -V
```

### 7. Installer Node.js 22

Node.js 18 (version par défaut sur Ubuntu 24.04) n'est **pas compatible** avec Vite 6. Il faut Node.js 20.19+ ou 22.x.

```bash
# Supprimer l'ancienne version si présente
sudo apt remove -y nodejs npm
sudo apt autoremove -y

# Installer Node.js 22 via NodeSource
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Vérifier
node -v   # v22.x.x
npm -v
```

### 8. Cloner le projet

```bash
sudo mkdir -p /var/www/amana-planning
sudo chown $USER:$USER /var/www/amana-planning
git clone https://github.com/votre-organisation/amana-planning.git /var/www/amana-planning
cd /var/www/amana-planning
```

### 9. Installer les dépendances PHP

```bash
composer install
```

### 10. Installer les dépendances Node et compiler le CSS

```bash
npm install
npm run build
```

> **`npm run build`** génère le dossier `public/build/` contenant le CSS Tailwind compilé. Ce dossier est commité dans git — en production, cette étape n'est donc pas nécessaire.
>
> En développement, utilisez `npm run dev` pour le hot reload automatique lors des modifications de vues Blade.

### 11. Configurer l'environnement

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

SESSION_DRIVER=file
SESSION_LIFETIME=120

QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=log

MAKE_WEBHOOK_URL=
```

> **`MAIL_MAILER=log`** : les emails s'écrivent dans `storage/logs/laravel.log` au lieu d'être envoyés — pratique en développement.
>
> **`HEURE_COURS` est obsolète** et ignorée. L'heure du cours est gérée via **Paramètres → Heure du cours** dans l'interface.

### 12. Permissions des dossiers

```bash
sudo chown -R $USER:www-data /var/www/amana-planning
sudo chmod -R 755 /var/www/amana-planning
sudo chmod -R 775 /var/www/amana-planning/storage
sudo chmod -R 775 /var/www/amana-planning/bootstrap/cache
```

### 13. Migrations et seeders

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

### 14. Lien de stockage public

```bash
php artisan storage:link
```

### 15. Configurer Nginx

```bash
sudo nano /etc/nginx/sites-available/amana-planning
```

```nginx
server {
    listen 80;
    server_name localhost;

    root /var/www/amana-planning/public;
    index index.php index.html;

    access_log /var/log/nginx/amana-planning.access.log;
    error_log  /var/log/nginx/amana-planning.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/amana-planning /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### 16. Vérifier l'installation

Ouvrir <http://localhost>. La page de connexion AMANA Planning doit s'afficher.

```bash
# En cas de problème
sudo tail -f /var/log/nginx/amana-planning.error.log
tail -f /var/www/amana-planning/storage/logs/laravel.log
```

---

## Déploiement en production (IONOS)

### Prérequis côté serveur

- PHP 8.2+ (configurer dans le panneau IONOS → Configuration PHP)
- Composer disponible
- **Node.js non requis** — `public/build/` est livré via git

### 1. Cloner le projet

```bash
cd /var/www/vhosts/votredomaine.fr/httpdocs
git clone https://github.com/votre-organisation/amana-planning.git .
```

### 2. Installer les dépendances PHP

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configurer l'environnement de production

```bash
cp .env.example .env
php artisan key:generate
```

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
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=votredomaine.ionos.fr

QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=                        # Laisser VIDE — pas "null"
MAIL_HOST=smtp.ionos.fr
MAIL_PORT=587
MAIL_USERNAME=votre@email.fr
MAIL_PASSWORD=mot_de_passe_smtp
MAIL_FROM_ADDRESS=votre@email.fr
MAIL_FROM_NAME="AMANA Planning"

MAKE_WEBHOOK_URL=https://hook.eu2.make.com/...
MAKE_WEBHOOK_APIKEY=...

APP_EMERGENCY_KEY=                  # Laisser vide sauf urgence
```

> ⚠️ **`MAIL_SCHEME=null`** (la chaîne littérale) bloque silencieusement STARTTLS. Utiliser `MAIL_SCHEME=` (vide) ou `MAIL_SCHEME=tls`.

### 4. Migrations et seeders

```bash
php artisan migrate --force
php artisan db:seed --force
```

### 5. Lien de stockage public

```bash
php artisan storage:link
```

### 6. Mise en cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7. Build frontend — pas nécessaire en production

Le dossier `public/build/` est inclus dans git. IONOS n'a pas besoin de Node.js.

> Si vous avez modifié des vues ou du CSS en local, faites `npm run build` localement, commitez `public/build/`, et déployez.

### 8. Configurer le document root

Dans le panneau IONOS : **Hébergement Web > votre domaine > Répertoire Web** → `/httpdocs/public`.

Ou via `.htaccess` à la racine du `httpdocs` :

```apache
RewriteEngine On
RewriteRule ^(.*)$ public/$1 [L]
```

### 9. Permissions

```bash
find . -type f -not -path "./storage/logs/*" -exec chmod 664 {} \;
find . -type d -not -name "logs" -exec chmod 775 {} \;
chmod -R o+w storage bootstrap/cache
```

### 10. Script de mise à jour

Créer `deploy.sh` à la racine :

```bash
#!/bin/bash
set -e

echo "==> Mise à jour du code..."
git pull origin main

echo "==> Dépendances PHP..."
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
2. Se connecter avec `admin@amana.fr` / `changeme123!`

### Changer le mot de passe

#### Via l'interface (recommandé)

1. Se déconnecter
2. Aller sur `/mot-de-passe-oublie`
3. Saisir `admin@amana.fr`
4. Suivre le lien reçu par email (ou dans `storage/logs/laravel.log` si `MAIL_MAILER=log`)

#### Via l'outil d'urgence `/urgence-hash` (si SMTP non opérationnel)

1. Ajouter `APP_EMERGENCY_KEY=une-cle-secrete` dans `.env` production
2. Visiter `https://votredomaine.com/urgence-hash?key=une-cle-secrete`
3. Générer le hash bcrypt
4. Exécuter la requête SQL affichée dans phpMyAdmin
5. **Retirer `APP_EMERGENCY_KEY`** du `.env` après usage

---

## Référence des routes principales

| Méthode | URL                          | Nom                         | Accès              | Description                             |
| ------- | ---------------------------- | --------------------------- | ------------------ | --------------------------------------- |
| GET     | `/`                          | —                           | Public             | Redirige vers `/planning`               |
| GET     | `/login`                     | `login`                     | Public             | Formulaire de connexion                 |
| GET     | `/inscription`               | `inscription`               | Public             | Formulaire d'inscription publique       |
| GET     | `/planning`                  | `planning.index`            | Connecté           | Vue principale du planning              |
| GET     | `/mon-planning`              | `mon-planning`              | Connecté           | Vue personnelle                         |
| GET     | `/planning/stats`            | `planning.statistics`       | Connecté           | Statistiques                            |
| GET     | `/planning/export`           | `planning.export.form`      | Connecté           | Formulaire export PDF                   |
| POST    | `/planning/export/pdf`       | `planning.export.pdf`       | Connecté           | Génération PDF                          |
| GET     | `/planning/generer`          | `planning.generate.form`    | Gestionnaire+Admin | Formulaire de génération                |
| POST    | `/planning/generer`          | `planning.generate`         | Gestionnaire+Admin | Génération effective                    |
| POST    | `/planning/generer/apercu`   | `planning.preview`          | Gestionnaire+Admin | Prévisualisation dry-run                |
| POST    | `/planning/overlap/cancel`   | `planning.overlap.cancel`   | Gestionnaire+Admin | Annule la confirmation de chevauchement |
| POST    | `/planning/rollback`         | `planning.rollback`         | Gestionnaire+Admin | Rollback post-génération                |
| POST    | `/planning/rollback/dismiss` | `planning.rollback.dismiss` | Gestionnaire+Admin | Ferme la session de rollback            |
| GET     | `/absences`                  | `absences.index`            | Connecté           | Liste des absences                      |
| GET     | `/restrictions`              | `restrictions.index`        | Connecté           | Grille des disponibilités               |
| GET     | `/evenements`                | `evenements.index`          | Connecté           | Liste des événements                    |
| GET     | `/parametres`                | `settings.index`            | Gestionnaire+Admin | Paramètres de l'application             |
| GET     | `/personnes`                 | `personnes.index`           | Admin              | Liste des membres                       |
| GET     | `/admin/candidatures`        | `admin.candidatures.index`  | Admin              | Tableau de bord des candidatures        |
| GET     | `/admin/echanges`            | `admin.echanges.index`      | Gestionnaire+Admin | Gestion des échanges                    |
| GET     | `/diagnostic-mail`           | `diagnostic.mail.index`     | Admin              | Diagnostic SMTP                         |
| GET     | `/echanges`                  | `echanges.index`            | Connecté           | Mes échanges                            |

---

## Résolution des problèmes courants

### Erreur 500 au premier déploiement

```bash
tail -f storage/logs/laravel.log
sudo chmod -R 775 storage bootstrap/cache
php artisan cache:clear && php artisan config:clear
```

### Nginx retourne 502 Bad Gateway

```bash
sudo systemctl status php8.4-fpm
sudo systemctl restart php8.4-fpm
ls -la /run/php/php8.4-fpm.sock
```

### Erreur « Table sessions doesn't exist »

```bash
php artisan migrate --force
```

### Les emails ne partent pas

```bash
tail -f storage/logs/laravel.log
# Vérifier que MAIL_SCHEME n'est pas "null" (chaîne littérale)
# Tester via /diagnostic-mail dans l'interface
```

### La page s'affiche sans CSS (style manquant)

Le dossier `public/build/` est absent ou vide.

```bash
# En local
npm install
npm run build

# Puis commiter public/build/ dans git
git add public/build/
git commit -m "Build Tailwind CSS"
git push
```

### Erreur npm « vite requires Node.js version 20.19+ »

```bash
sudo apt remove -y nodejs npm && sudo apt autoremove -y
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v   # v22.x.x
rm -rf node_modules package-lock.json
npm install && npm run build
```

### Erreur npm « ERESOLVE — laravel-vite-plugin peer vite »

`package.json` contient `"vite": "^8.x"` — incompatible avec `laravel-vite-plugin`. Corriger :

```json
"vite": "^6.0.0"
```

```bash
rm -rf node_modules package-lock.json
npm install && npm run build
```

### Warning PostCSS « @import must precede all other statements »

Dans `resources/css/app.css`, les `@import` doivent précéder les directives `@tailwind`. Vérifier l'ordre :

```css
@import url("https://fonts.googleapis.com/...");
@import "../../public/css/custom.css";

@tailwind base;
@tailwind components;
@tailwind utilities;
```

### Page blanche après config:cache

```bash
php artisan config:clear && php artisan config:cache
```

### Nginx retourne 403 Forbidden

```bash
grep -r "root" /etc/nginx/sites-enabled/amana-planning
# Vérifier que root pointe vers /var/www/amana-planning/public
```

### La prévisualisation (Aperçu) est lente

Normal — le dry-run exécute l'algorithme complet. Pour des plannings > 20 semaines :

```bash
sudo nano /etc/php/8.4/fpm/php.ini
# max_execution_time = 120
sudo systemctl restart php8.4-fpm
```

### Problème de session (déconnexion intempestive) en production

```dotenv
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=votredomaine.fr
```

```bash
php artisan migrate --force   # Crée la table sessions si absente
```
