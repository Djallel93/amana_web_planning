# Tests — amana_web_planning

## Lancer la suite en local

```bash
# 1. Deux bases vides (noms imposés par phpunit.xml)
mysql -uroot -e "CREATE DATABASE amana_planning_test; CREATE DATABASE amana_commun_test;"

# 2. Si votre utilisateur MySQL n'est pas root sans mot de passe :
export DB_USERNAME=... DB_PASSWORD=...

php artisan test
```

## Décision : MySQL 8, pas SQLite

Le code de production ne tourne pas sur SQLite :

- `FIELD(...)` dans `orderByRaw` : `RoleService::planningRoles()` /
  `currentRoleCode()`, `PersonnesController::index()`, `EchangeController`
  (liste). Les tester sur SQLite reviendrait à ne pas tester la vraie requête.
- Deux connexions : la connexion par défaut (`plan_*`, `ref_taches`,
  `ref_evenements`…) et `commun` (`ref_personnes`, `ref_roles`, `audit_logs`…,
  paquet `amana/shared`), déclarée en `driver => 'mysql'`.

La CI utilise donc un service `mysql:8.0` (voir `.github/workflows/deploy.yaml`,
job `test`), avec deux bases : `amana_planning_test` et `amana_commun_test`.

Garde-fous contre une purge accidentelle de `amana_commun` :

1. `phpunit.xml` **force** (`force="true"`) les noms de bases ; ni `.env` ni une
   variable d'environnement ne les remplacent.
2. `Tests\Concerns\RefreshesBothDatabases` refuse de migrer si une des deux
   connexions ne vise pas une base finissant par `_test`.

## Deux familles de tests

| Famille | Étend | Base de données |
|---|---|---|
| Logique pure (ex. `DateHelperTest`) | `PHPUnit\Framework\TestCase` | non |
| Application (Feature, services avec BD) | `Tests\TestCase` | oui, avec `use RefreshesBothDatabases` |

`RefreshesBothDatabases` crée le schéma une fois par exécution (`migrate:fresh`
puis `amana:migrate-shared --fresh` — les migrations partagées ne sont pas vues
par `migrate`), puis enveloppe chaque test dans une transaction sur les deux
connexions.

## Consommation de `amana/shared` en CI

Version **épinglée** par `composer.lock` (dépôt VCS, comme le job `build`).
`composer.local.json` (chemin `../amana_shared`, git-ignoré) n'existe pas en CI
et n'est utile qu'en co-développement local : en local, lancer la suite avec ce
fichier présent teste donc le code non publié de `amana_shared`.

## Pièges à connaître

- Modèles « côté many » d'une relation partant de `Personne` (`Absence`,
  `Restriction`, `CreneauTache`) : ils doivent déclarer `getConnectionName()`,
  sinon Eloquent hérite de `commun`. Toute nouvelle factory/fixture de ces
  modèles doit être vérifiée contre ce piège.
- `SchedulerMain::generateSchedule()` ouvre sa propre transaction. Dans un test,
  elle s'imbrique (SAVEPOINT) dans celle du trait : le rollback du mode dry-run
  est observable, mais un `commit()` interne ne persiste rien au-delà du test.
- Google Calendar et le webhook Make.com ne sont jamais appelés : `Http::fake()`,
  double de test lié à `GoogleCalendarService`, `Bus::fake()`/`Queue::fake()`.
- Interface front (Vue/TS) : hors périmètre pour l'instant, pas de Vitest.

## Factories (`database/factories`)

- `Personne` : validée par défaut ; états `enAttente()`, `suspendu()`, `archive()`,
  `dansLaRotationDepuis()`, et `admin()` / `gestionnaire()` / `membre()` /
  `benevole()` (application `planning` et rôle créés à la demande dans `commun`,
  idempotent — rien à seeder). Mot de passe : `PersonneFactory::MOT_DE_PASSE`.
- `Tache` : code unique auto-généré ; pour un code métier (`entree`,
  `amana_food`…) utiliser `TacheFactory::pourCode()` (find-or-create) —
  `annulation_cours` existe déjà, posée par une migration.
- `Creneau` et `Bilan` : dates uniques par compteur à partir de 2031-01-01.
- `CreneauTache` : clé primaire composite, modèle sans `$primaryKey` — pas de
  `->fresh()` ni de second `->save()`.
- `Echange` : cohérent par défaut (les deux lignes `plan_creneaux_taches`
  sont assignées au demandeur et à la cible).
- `Personne::peutFaireTache()` garde un cache **statique** `Tache::pluck('code','id')`
  entre tests : les ids de tâches changent d'un test à l'autre (transaction annulée,
  auto-increment non remis à zéro) — le tester en réinitialisant ce cache par
  réflexion dans `setUp()`.

## Tests de logique pure (sans base de données)

`tests/Unit/` : `PHPUnit\Framework\TestCase` directement, ni conteneur Laravel ni MySQL.
Trois aides dans `tests/Support/` :

- `PersonneDeTest` : faux `Personne` (nom, prénom, restrictions par tâche/jour,
  `date_debut_planning`) pour `RotationEngine`.
- `FauxSettings::definir([...])` : pré-remplit le cache statique de `Setting::get()`
  (à remettre à zéro en `tearDown` avec `::reinitialiser()`).
- `LogEnregistre::installer()` : remplace `Log` via `Log::swap()` pour vérifier les
  avertissements sans application démarrée.

Les modèles Eloquent se construisent **sans base** avec
`(new Modele())->setRawAttributes([...])` + `setRelation()`. Ne pas passer par
`new Modele([...])` : le remplissage d'un attribut casté en date résout la connexion.
Une valeur `Y-m-d H:i:s` sur un attribut `'date'` a le même effet — utiliser `Y-m-d`.

## Caches statiques

Trois caches statiques survivent d'un test à l'autre : `Setting::$cache` (amana_shared),
`AuditHelper::$applicationId` (pas de `clearCache()` côté planning) et
`Personne::$tacheCodesById`. `Tests\TestCase::setUp()` les remet à zéro ; un test
qui n'étend pas `Tests\TestCase` et lit ces caches doit le faire lui-même.

## Tests avec base de données : ce qu'il faut savoir avant d'en écrire

- **Trait** : `use RefreshesBothDatabases;` (schéma créé une fois, transaction par test sur les
  deux connexions). Scénarios prêts à l'emploi : `use CreeDonneesPlanning;` (`tachesDeRotation()`,
  `personnesValidees(n)`, `assigner($personne, $date, $code)`, `idPersonneDuCreneau()`…).
- **Clés TINYINT** : `ref_taches`, `ref_applications`, `ref_roles`, `ref_settings` ont un `id`
  TINYINT UNSIGNED (max 255) et un rollback ne remet pas l'AUTO_INCREMENT à zéro. Le trait
  les remet à zéro après chaque test (`ALTER TABLE … AUTO_INCREMENT = 1`, exécuté une fois la
  transaction fermée). Sans cela la suite entière échoue vers le 170e test avec
  « Out of range value for column 'id' ».
- **`Personne::nom` est mis en majuscules** par le modèle : comparer aux valeurs relues, pas à la saisie.
- **Faux e-mails / jobs / HTTP** : `Notification::fake()` et `Bus::fake()` par test ;
  `Http::preventStrayRequests()` est posé par `Tests\TestCase` (la file de test est `sync` :
  un job non simulé partirait réellement vers Google).
- **Tests de caractérisation** : un test dont le docblock commence par `CARACTÉRISATION` fige un
  comportement actuel *discutable* (pas nécessairement voulu). Ils sont listés par
  `grep -rn CARACTÉRISATION tests`. Si le comportement est corrigé, c'est CE test qu'il faut
  inverser, pas le code qu'il faut rétablir.
- **Espion de générateur** : `Tests\Support\SchedulerEspion` remplace
  `regenerateFromImpactedDate()` pour tester les services de régénération sans lancer la génération.
