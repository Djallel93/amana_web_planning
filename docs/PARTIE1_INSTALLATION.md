# PARTIE 1 — Environnement de développement local (Pop!_OS 24.04)

## Vue d'ensemble

Tu vas installer dans cet ordre :
1. PHP 8.2 + extensions
2. Composer (gestionnaire de paquets PHP)
3. MySQL 8
4. Node.js (optionnel mais utile)
5. Laravel via Composer
6. Configurer la base de données locale

---

## Étape 1 — Mettre à jour le système

Ouvre un terminal (`Ctrl+Alt+T`) et tape :

```bash
sudo apt update && sudo apt upgrade -y
```

---

## Étape 2 — Installer PHP 8.2 et ses extensions

Pop!_OS 24.04 est basé sur Ubuntu 24.04 qui inclut PHP 8.3 par défaut.
Pour avoir exactement PHP 8.2 (comme IONOS), utilise le dépôt Ondrej :

```bash
# Ajouter le dépôt PHP d'Ondrej (maintenu, fiable)
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Installer PHP 8.2 et toutes les extensions nécessaires à Laravel
sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-gd \
    php8.2-tokenizer \
    php8.2-ctype \
    php8.2-fileinfo \
    php8.2-pdo
```

Vérifier l'installation :
```bash
php8.2 --version
# Doit afficher : PHP 8.2.x (cli)
```

Définir PHP 8.2 comme version par défaut :
```bash
sudo update-alternatives --set php /usr/bin/php8.2
php --version
```

---

## Étape 3 — Installer Composer

Composer est le gestionnaire de dépendances PHP (équivalent de npm pour Node).

```bash
# Télécharger l'installateur
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php

# Vérifier l'intégrité (optionnel mais recommandé)
HASH=$(curl -sS https://composer.github.io/installer.sig)
php -r "if (hash_file('SHA384', '/tmp/composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('/tmp/composer-setup.php'); }"

# Installer globalement
sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
```

Vérifier :
```bash
composer --version
# Doit afficher : Composer version 2.x.x
```

---

## Étape 4 — Installer MySQL 8

```bash
sudo apt install -y mysql-server mysql-client
```

Démarrer MySQL et l'activer au démarrage :
```bash
sudo systemctl start mysql
sudo systemctl enable mysql
sudo systemctl status mysql
# Doit afficher : Active: active (running)
```

Sécuriser MySQL (IMPORTANT) :
```bash
sudo mysql_secure_installation
```
Réponds aux questions :
- VALIDATE PASSWORD COMPONENT → **n** (non, pour simplifier en dev)
- Remove anonymous users → **y**
- Disallow root login remotely → **y**
- Remove test database → **y**
- Reload privilege tables → **y**

Créer un utilisateur MySQL pour Laravel :
```bash
sudo mysql

# Dans le shell MySQL :
CREATE DATABASE amana_planning CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'amana_user'@'localhost' IDENTIFIED BY 'motdepasse_dev_123';
GRANT ALL PRIVILEGES ON amana_planning.* TO 'amana_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Vérifier la connexion :
```bash
mysql -u amana_user -p amana_planning
# Entrer le mot de passe : motdepasse_dev_123
# Doit afficher le shell MySQL
EXIT;
```

---

## Étape 5 — Installer Node.js (optionnel)

Même si on n'utilise pas npm/Vite, Node.js peut être utile.

```bash
# Via le gestionnaire de versions NVM (recommandé)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
source ~/.bashrc
nvm install --lts
node --version   # v20.x.x
npm --version    # 10.x.x
```

---

## Étape 6 — Créer le projet Laravel

```bash
# Se placer dans ton répertoire de projets
cd ~/projets   # ou mkdir ~/projets && cd ~/projets

# Créer le projet Laravel (version 11)
composer create-project laravel/laravel amana-planning "^11.0"

cd amana-planning
```

Structure créée par Laravel :
```
amana-planning/
├── app/                    ← Code PHP de l'application
│   ├── Http/
│   │   ├── Controllers/    ← Les contrôleurs
│   │   ├── Middleware/     ← Les middlewares (auth, etc.)
│   │   └── Requests/       ← Validation des formulaires
│   ├── Models/             ← Les modèles Eloquent (= tables DB)
│   └── Services/           ← La logique métier
├── config/                 ← Fichiers de configuration
├── database/
│   └── migrations/         ← Définition des tables (= CREATE TABLE)
├── public/                 ← Seul dossier exposé au web (index.php)
├── resources/
│   └── views/              ← Les vues Blade (= HTML + PHP)
├── routes/
│   └── web.php             ← Toutes les routes de l'application
├── storage/                ← Logs, fichiers uploadés, cache
├── .env                    ← Configuration locale (JAMAIS commité sur Git)
└── artisan                 ← Outil en ligne de commande de Laravel
```

---

## Étape 7 — Configurer le fichier .env

Le fichier `.env` contient toute la configuration sensible (DB, clés, etc.).
Il ne doit JAMAIS être mis sur Git (il est dans .gitignore par défaut).

```bash
# Ouvrir .env avec un éditeur
nano .env
```

Modifier ces valeurs :
```dotenv
APP_NAME="AMANA Planning"
APP_ENV=local
APP_KEY=   # Sera généré automatiquement
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amana_planning
DB_USERNAME=amana_user
DB_PASSWORD=motdepasse_dev_123

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

Générer la clé de l'application (obligatoire) :
```bash
php artisan key:generate
# Affiche : Application key set successfully.
# La clé est maintenant dans APP_KEY= dans ton .env
```

---

## Étape 8 — Importer le schéma de base de données

```bash
# Option A : Importer directement le fichier SQL existant
mysql -u amana_user -p amana_planning < /chemin/vers/ton/export.sql

# Option B : Utiliser les migrations Laravel (recommandé — voir Partie 3)
php artisan migrate
```

---

## Étape 9 — Lancer le serveur de développement

```bash
# Dans le dossier du projet
php artisan serve
```

Ouvre ton navigateur sur : **http://localhost:8000**

Tu dois voir la page d'accueil Laravel (fond sombre avec le logo).

Pour arrêter le serveur : `Ctrl+C`

---

## Checklist de vérification

Avant de passer à la suite, vérifie chaque point :

```bash
# ✅ PHP 8.2
php --version

# ✅ Composer
composer --version

# ✅ MySQL en cours d'exécution
sudo systemctl status mysql

# ✅ Connexion DB depuis Laravel
php artisan db:show
# Doit afficher les infos de ta DB sans erreur

# ✅ Toutes les routes Laravel accessibles
php artisan route:list

# ✅ Pas d'erreur dans les logs
tail -f storage/logs/laravel.log
```

Si `php artisan db:show` renvoie une erreur de connexion :
```bash
# Vérifier que MySQL tourne
sudo systemctl restart mysql
# Vérifier les credentials dans .env
# Retester la connexion manuellement
mysql -u amana_user -p -h 127.0.0.1
```

---

## Erreurs fréquentes et solutions

**Erreur : `Class 'PDO' not found`**
```bash
sudo apt install php8.2-pdo php8.2-mysql
sudo systemctl restart php8.2-fpm
```

**Erreur : `permission denied on storage/`**
```bash
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

**Erreur : `SQLSTATE[HY000] [1045] Access denied`**
```bash
# Vérifier le mot de passe dans .env
# Recréer l'utilisateur MySQL si nécessaire
sudo mysql
DROP USER 'amana_user'@'localhost';
CREATE USER 'amana_user'@'localhost' IDENTIFIED BY 'motdepasse_dev_123';
GRANT ALL PRIVILEGES ON amana_planning.* TO 'amana_user'@'localhost';
FLUSH PRIVILEGES;
```
