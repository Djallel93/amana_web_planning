# Tests visuels Playwright (local uniquement)

Mis en place pour la migration Tailwind 3 → 4 (14/10/2026) : comparer l'avant et
l'après (captures, styles calculés, fumée par rôle). **Aucun job CI** : tout se
lance en local, contre l'application Docker (`docs/docker.md`).

## 1. Prérequis

- Node 22 (comme la CI) et `npm ci` exécuté dans ce dépôt.
- L'application qui tourne avec des données seedées : `make up && make fresh`
  (http://localhost:8001). Les comptes `e2e-<rôle>@amana.test` (mot de passe
  `e2e-password`) sont créés par `make fresh`.
- Chromium pour Playwright : `npx playwright install chromium` (une fois).
- Accès Internet pour les polices Google (Sora, Plus Jakarta Sans) : sans elles,
  les captures « avant » et « après » doivent être prises dans les mêmes
  conditions, sinon les comparaisons échouent.

Variables optionnelles : `E2E_BASE_URL` (défaut `http://localhost:8001`),
`E2E_PASSWORD` (défaut `e2e-password`).

## 2. Ce qui est testé

| Fichier | Contenu |
| --- | --- |
| `e2e/auth.setup.ts` | une connexion par rôle (admin, gestionnaire, membre, benevole) ; sessions dans `e2e/.auth/` |
| `e2e/smoke.spec.ts` | chaque page répond < 400, sans erreur JavaScript ; accès interdits refusés ; compte « en attente » refusé |
| `e2e/screenshots.spec.ts` | captures pleine page des pages publiques + pages admin + pages communes en membre |
| `e2e/computed-style.spec.ts` | styles calculés (rayon, ombre, couleurs, outline, police, padding, transition) de ~35 sélecteurs sur 4 pages |
| `e2e/pages.ts` | inventaire des pages et des rôles autorisés (**à confirmer au premier passage**) |

Quatre projets Playwright : `desktop-light`, `desktop-dark`, `mobile-light`,
`mobile-dark` (1280×800 et 390×844 ; thème via `localStorage['amana-theme']`).
Horloge figée (vendredi 16/10/2026), mouvements réduits, animations désactivées.

## 3. Avant la migration : créer les références (commit Tailwind 3)

À faire sur le commit **avant** le basculement (sans ce dépôt `postcss.config.js`
ni `@tailwindcss/vite`), application lancée avec `make fresh` :

```bash
git checkout <commit-tailwind-3> -- .          # ou une copie propre du dépôt
npm ci && npx playwright install chromium
npm run test:e2e -- --update-snapshots          # captures de référence
E2E_RECORD=1 npm run test:e2e -- computed-style # styles calculés de référence
```

Les références (`e2e/__screenshots__/`, `e2e/__computed__/`) sont **ignorées par
git** : gardez ce dossier de côté (ou refaites-les) tant que la migration n'est
pas validée. Les specs et la config, elles, sont versionnées.

## 4. Après la migration : comparer

```bash
npm ci
npm run test:e2e                # compare avec les références
npm run test:e2e:report         # rapport HTML (e2e/report)
```

Seuils (`playwright.config.ts`) : 0,1 par pixel, 0,2 % de pixels différents au
maximum. Les styles calculés comparent rayon/ombre à l'identique et les couleurs
en RVB à ±1. Des écarts attendus existent : palette par défaut de Tailwind 4 (teintes
rose, sky, violet… et case à cocher du plugin forms), logo de connexion
`w-30 h-30` à 120 px, curseur flèche sur les boutons (D11), `outline-hidden`
(`outline-style: none` au lieu d'un contour transparent), `divide-y` (bordure sur
le bas de l'élément précédent), `transition` qui inclut `outline-color` : à
lister et à faire valider.

## 5. Mettre à jour les références

Après validation d'un écart voulu : `npm run test:e2e -- --update-snapshots <fichier>`
(ou `E2E_RECORD=1` pour les styles calculés).

## 6. Lire le rapport

`npm run test:e2e:report` ouvre le rapport HTML : pour chaque capture en échec,
les onglets *Diff*, *Actual*, *Expected* et *Side by side* ; la trace (`trace.zip`)
est conservée pour les échecs.

## 7. Dépannage

- **Tout échoue en 401/redirection vers /login** : relancer le projet `setup`
  (`npm run test:e2e -- --project=setup`) ; vérifier `make fresh` (comptes e2e).
- **Captures instables** : polices pas encore chargées (réseau), données non
  seedées de façon identique (refaire `make fresh` avant chaque série), animations
  ou images distantes ; relancer deux fois à vide avant de conclure.
- **Pages « interdites » qui passent (ou l'inverse)** : corriger les `roles`
  dans `e2e/pages.ts` selon `routes/web.php` / `EnsureRole`.
- **Port différent** : `E2E_BASE_URL=http://localhost:<port>`.
- **Chromium introuvable** : `npx playwright install chromium`.
