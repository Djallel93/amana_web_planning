# Sécurité des dépendances

Comment AMANA Planning détecte les failles connues (CVE / GHSA) dans ses
dépendances PHP (Composer) et JavaScript (npm), et que faire quand il y en a.

## 1. Ce qui surveille quoi

| Mécanisme                           | Ce qu'il fait                                                  | Quand            | Où                                  |
| ----------------------------------- | -------------------------------------------------------------- | ---------------- | ----------------------------------- |
| Dependabot alerts                   | Signale une faille touchant une dépendance                     | en continu       | GitHub > Security > Dependabot      |
| Dependabot security updates         | Ouvre une PR de correction                                     | en continu       | PR vers `main` (branche par défaut) |
| Dependabot version updates          | Ouvre des PR de mise à jour (`.github/dependabot.yml`)         | lundi 07:00 (Paris) | PR vers `develop`                |
| Audit hebdomadaire (`security.yaml`) | `composer audit` + `npm audit`                                 | lundi 06:00 UTC  | onglet Actions                      |
| Porte de qualité (`tests.yaml`)     | Tests, types, mise en forme. **Pas d'audit, volontairement.**  | chaque push      | CI / déploiements                   |

L'audit est séparé de la porte de qualité pour qu'une faille publiée demain ne
bloque pas le déploiement d'un changement sans rapport. Un audit en échec
déclenche un e-mail GitHub, sans toucher aux déploiements.

## 2. À activer côté GitHub (une seule fois)

Les fichiers du dépôt ne suffisent pas : ces réglages se font dans l'interface.

- **Settings > Code security** : activer *Dependency graph*, *Dependabot alerts*,
  *Dependabot security updates*, et *Secret scanning* avec *Push protection*
  (détecte les secrets committés par erreur).
- **Settings > Secrets and variables > Dependabot** : ajouter le secret
  `AMANA_REPOS_PAT` (même valeur que le secret Actions du même nom). Les
  workflows déclenchés par Dependabot ne voient pas les secrets Actions : sans
  cela, l'étape « Configure git access for private amana/* repos » de la porte
  de qualité échoue sur les PR Dependabot.
- Si `amana_shared` / `amana_shared_ui` sont privés, Dependabot a aussi besoin
  d'un bloc `registries` de type `git` dans `dependabot.yml` (non configuré :
  au 04/10/2026 ces dépôts sont lisibles sans authentification).

Après la première fusion, vérifier dans **Insights > Dependency graph >
Dependabot** que les trois écosystèmes (composer, npm, github-actions) se
mettent à jour sans erreur.

## 3. Traiter une PR Dependabot

- **Mise à jour de version** (vers `develop`) : la porte de qualité s'exécute
  dessus. Lire les notes de version, fusionner si elle est verte ; la
  promotion vers `main` suit le circuit habituel.
- **Version majeure** : PR séparée, jamais regroupée. Lire le guide de
  migration. Pour l'écarter : commenter `@dependabot ignore this major version`.
- **Correctif de sécurité** (vers `main`) : vérifier que la porte de qualité
  est verte, fusionner, puis répercuter le correctif sur `develop`.
- Ne pas lancer `npm audit fix --force` ni un `composer update` global « pour
  faire taire » un avertissement : cela mélange des mises à jour majeures non
  relues dans un seul changement.

## 4. Commandes locales

```bash
composer audit --locked   # dépendances PHP, d'après composer.lock
npm audit --omit=dev      # dépendances JavaScript de production (déployées)
npm audit                 # + outils de développement (build uniquement)
```

Une faille dans une dépendance de **production** est à traiter en priorité ;
dans une dépendance de **développement** (outil de build jamais déployé), le
risque réel est en général faible : à évaluer, sans urgence.

## 5. Constat connu au 04/10/2026

- `npm audit --omit=dev` : **0 faille** (dépendances de production).
- `npm audit` : 5 failles « high », **toutes en développement**, issues d'une
  seule chaîne : `tailwindcss` 3.x → `chokidar` / `fast-glob` → `micromatch` →
  `braces` (GHSA-vfj7-8cjw-p6xm, épuisement de pile sur des motifs glob très
  imbriqués). C'est un outil de build, jamais exécuté en production. La
  correction complète passe par Tailwind 4 (migration majeure) : à planifier
  comme un chantier à part, sans `--force`.

## 6. Quand l'audit hebdomadaire échoue

1. Ouvrir le journal du job : il nomme le paquet, l'avis (GHSA / CVE) et la
   version corrigée.
2. Production ou développement ? La faille est-elle atteignable dans notre
   usage ?
3. Corriger par une mise à jour (idéalement via la PR Dependabot déjà
   ouverte). S'il n'existe pas de correctif et que le risque est accepté,
   le consigner (avis, raison, date de revue) ; côté Composer on peut
   l'ignorer explicitement avec `config.audit.ignore` dans `composer.json`.
