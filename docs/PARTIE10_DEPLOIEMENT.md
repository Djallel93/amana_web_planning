# PARTIE 10 — Déploiement sur IONOS

## Prérequis côté IONOS

### Vérifier la version PHP
Connecte-toi à ton espace client IONOS → **Hébergement** → **Configuration PHP**.
- Active **PHP 8.2** (ou 8.3, compatible)
- Active les extensions : `pdo_mysql`, `mbstring`, `xml`, `zip`, `curl`, `bcmath`, `intl`

### Activer l'accès SSH
Dans l'espace client IONOS :
1. **Hébergement** → **SSH**
2. Active l'accès SSH
3. Note l'hôte SSH (ex : `ssh.cluster019.hosting.ovh.net`)
4. Ton identifiant est en général ton email ou un identifiant dédié

### Vérifier Composer disponible
```bash
# Connexion SSH
ssh tonidentifiant@tonserveur.ionos.com

# Vérifier Composer
composer --version
# Si absent : demander au support IONOS ou utiliser la méthode bin/ ci-dessous
```

---

## Étape 1 — Préparer le dépôt Git (en local)

```bash
cd ~/projets/amana-planning

# Initialiser Git
git init
git add .
git commit -m "Initial commit — AMANA Planning Laravel"

# Créer un dépôt sur GitHub/GitLab (privé recommandé)
# Puis :
git remote add origin https://github.com/toncompte/amana-planning.git
git push -u origin main
```

**Fichier `.gitignore` — vérifie qu'il contient ces lignes :**
```gitignore
/node_modules
/public/build
/vendor
/.env
/.env.backup
/storage/*.key
```

---

## Étape 2 — Structure des dossiers sur IONOS

Sur IONOS, le dossier public par défaut est `/htdocs` ou `/html`.
L'application Laravel doit être placée **en dehors** du dossier public :

```
/www/
├── amana-planning/          ← le projet complet (hors web)
│   ├── app/
│   ├── config/
│   ├── ...
│   └── public/              ← seul ce dossier doit être exposé
└── htdocs/                  ← dossier web public d'IONOS
    └── (contenu du dossier public/)
```

**Option A : Déplacer `public/` dans `htdocs/`**

Modifie `public/index.php` pour pointer vers le bon chemin :
```php
// public/index.php — modifier ces deux lignes
require __DIR__.'/../amana-planning/vendor/autoload.php';
$app = require_once __DIR__.'/../amana-planning/bootstrap/app.php';
```

**Option B : Configurer un VirtualHost** (si IONOS permet les VirtualHosts)

Dans ton `.htaccess` à la racine de `/htdocs` :
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ /amana-planning/public/$1 [L]
</IfModule>
```

---

## Étape 3 — Premier déploiement (via SSH)

```bash
# 1. Se connecter en SSH
ssh tonidentifiant@ssh.tondomaine.ionos.com

# 2. Aller dans le bon répertoire
cd /var/www/vhosts/tondomaine.fr/

# 3. Cloner le projet (hors htdocs)
git clone https://github.com/toncompte/amana-planning.git

cd amana-planning

# 4. Installer les dépendances PHP
composer install --no-dev --optimize-autoloader

# 5. Créer le fichier .env de production (voir section suivante)
cp .env.example .env
nano .env

# 6. Générer la clé de l'application
php artisan key:generate

# 7. Créer le lien symbolique storage
php artisan storage:link

# 8. Exécuter les migrations
php artisan migrate --force

# 9. Mettre en cache la configuration (performances)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 10. Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Étape 4 — Variables .env de production

```dotenv
APP_NAME="AMANA Planning"
APP_ENV=production
APP_KEY=           # Généré par php artisan key:generate
APP_DEBUG=false    # IMPORTANT : false en production !
APP_URL=https://tondomaine.fr

LOG_CHANNEL=single
LOG_LEVEL=error    # Moins verbeux en production

DB_CONNECTION=mysql
DB_HOST=ton-hote-mysql.ionos.com   # Voir espace client IONOS
DB_PORT=3306
DB_DATABASE=nom_de_ta_base         # Dans espace client IONOS
DB_USERNAME=ton_utilisateur_mysql
DB_PASSWORD=ton_mot_de_passe_mysql

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true         # HTTPS obligatoire

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

> **Où trouver les infos MySQL IONOS ?**
> Espace client → Base de données → Voir les détails de connexion

---

## Étape 5 — Créer le compte admin

Laravel utilise une table `users` par défaut. Pour créer le premier admin :

```bash
# Via tinker (console interactive Laravel)
php artisan tinker

# Dans tinker :
\App\Models\User::create([
    'name'     => 'Admin AMANA',
    'email'    => 'admin@amana.fr',
    'password' => \Illuminate\Support\Facades\Hash::make('TonMotDePasseSecurisé!'),
]);
exit
```

---

## Étape 6 — Déploiements suivants (mises à jour)

Script de mise à jour à placer dans `/amana-planning/deploy.sh` :

```bash
#!/bin/bash
# deploy.sh — Script de mise à jour

set -e   # Arrêter en cas d'erreur

echo "🚀 Déploiement AMANA Planning..."

# 1. Activer le mode maintenance (évite les requêtes pendant la MAJ)
php artisan down --message="Mise à jour en cours..." --retry=60

# 2. Récupérer les dernières modifications
git pull origin main

# 3. Mettre à jour les dépendances
composer install --no-dev --optimize-autoloader

# 4. Exécuter les nouvelles migrations
php artisan migrate --force

# 5. Vider et reconstruire les caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Désactiver le mode maintenance
php artisan up

echo "✅ Déploiement terminé !"
```

```bash
# Rendre le script exécutable
chmod +x deploy.sh

# L'utiliser pour les MAJ suivantes
./deploy.sh
```

---

## Vérifications post-déploiement

```bash
# 1. Tester la connexion à la base
php artisan db:show

# 2. Vérifier les routes
php artisan route:list

# 3. Tester les logs (pas d'erreurs)
tail -f storage/logs/laravel.log

# 4. Vérifier les permissions
ls -la storage/
ls -la bootstrap/cache/
```

**Checklist navigateur :**
- [ ] `https://tondomaine.fr/login` → page de connexion s'affiche
- [ ] Connexion avec email/mot de passe admin → redirige vers planning
- [ ] Création d'une personne test → enregistrement en BDD
- [ ] Génération d'un planning court (2 semaines)
- [ ] Les statistiques s'affichent sans erreur

---

## Erreurs fréquentes en production

**Erreur 500 après déploiement**
```bash
# Vérifier les logs
tail -100 storage/logs/laravel.log
# Souvent : APP_KEY manquante, permissions storage/, ou migration non exécutée
```

**`Class not found` après git pull**
```bash
composer dump-autoload --optimize
```

**Session expirée immédiatement**
```bash
# Vérifier que SESSION_SECURE_COOKIE=true seulement si HTTPS
# Vérifier que storage/framework/sessions/ est accessible en écriture
chmod -R 775 storage/framework/sessions/
```

**Images / CSS manquants**
```bash
# Vérifier APP_URL= dans .env (sans slash final, avec https://)
# Vérifier le lien symbolique storage
php artisan storage:link
```
