# Useful commands

## 1. Deployment commands

First deployment ever:

```bash
bashcomposer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Every time you push a change:

```bash
bashcomposer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The cache commands (config:cache, route:cache, view:cache) are important in production — they pre-compile everything so the app runs faster. Every time you change a .env, a route, or a Blade view, you need to rerun them.
A good practice is to put these in a deploy.sh script at the root of your project (you already have one in docs/PARTIE10_DEPLOIEMENT.md) so you just run ./deploy.sh after each push.

1. Old data — phpMyAdmin vs migrations
Use migrations for structure, phpMyAdmin (or seeders) for data. Never mix the two.
The rule is simple :
WhatWhereTable creation / modificationMigration fileReference data that never changes (villes, secteurs, quartiers)Seeder fileReal operational data (families, deliveries, history)phpMyAdmin import
For your geo data specifically, since it already exists and won't change, the cleanest approach is a seeder. That way it's version-controlled with your code and reproduced automatically on any fresh install.
bashphp artisan make:seeder GeoSeeder
Then in DatabaseSeeder.php call it :
php$this->call(GeoSeeder::class);
For your ref_* tables (vehicules, roles, taches) that you already seed — same thing, keep them in seeders.
For purely operational data that existed before (old planning history, old families etc.) — phpMyAdmin import is fine. That data belongs to a specific environment, not to the codebase.

2. First steps after deploying to production
Here is the exact sequence to follow :
Step 1 — Run the deploy commands
bashcomposer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
This creates all tables and inserts the default admin account <admin@amana.fr> with password changeme123!.

Step 2 — Log in as default admin and change the password
Go to <https://yourapp.ionos.fr/login>, log in with :

Email : <admin@amana.fr>
Password : changeme123!

Then immediately go to the password reset page to change it :
<https://yourapp.ionos.fr/mot-de-passe-oublie>

Step 3 — Create your own personal admin account
You have two options :
Option A — use the registration form like any member would, then promote yourself to admin via php artisan amana:reset-admin --email=<toi@email.fr> which also ensures the admin role is attached.
Option B — simpler, directly via tinker in SSH :
bashphp artisan tinker
php$p = App\Models\Personne::create([
    'nom'                  => 'TonNom',
    'prenom'               => 'TonPrenom',
    'email'                => 'toi@email.fr',
    'password'             => bcrypt('TonMotDePasse'),
    'statut'               => 'Validé',
    'date_debut_planning'  => now(),
    'email_verified_at'    => now(),
    'tirelire'             => false,
]);

$app    = App\Models\Application::where('code', 'planning')->first();
$role   = App\Models\Role::where('code', 'admin')->where('id_application', $app->id)->first();

DB::table('ref_personnes_roles')->insert([
    'id_personne'      => $p->id,
    'id_role'          => $role->id,
    'date_attribution' => now()->toDateString(),
]);

Step 4 — Registering members and volunteers
There are three paths depending on the person :
Path A — New person, self-registers
They go to <https://yourapp.ionos.fr/inscription>, fill in the form, click submit. Their status is En attente. You receive an email notification. You go to /admin/candidatures, click Validate. They receive an email with a link to create their password. They click the link, create their password, and can now log in.
Path B — Person already exists in ref_personnes (imported from old system)
They don't need to register. You go to /admin/candidatures → this won't show them because their status isn't En attente. Instead go to /personnes, find them, make sure their status is Validé and they have a date_debut_planning. Then in SSH :
bashphp artisan amana:reset-admin --email=<leur@email.fr>
This sets a temporary password and ensures the role is attached. Or use the password reset link from /mot-de-passe-oublie and send them the link manually.
Path C — You create an account directly for someone (admin action)
Go to /personnes → create → fill in the form → save. Then send them the reset link from /mot-de-passe-oublie so they can create their own password.
