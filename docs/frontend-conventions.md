# Conventions frontend (Vue 3 / TypeScript / Inertia)

Règles à suivre pour tout changement dans `resources/js/`. Elles sont
vérifiées par `npm run lint`, `npm run type-check` et `npm run format:check`,
tous exécutés par la CI (`.github/workflows/tests.yaml`).

**Principe général : corriger la cause, ne pas contourner la règle.** Aucune
règle ESLint n'est désactivée (ni globalement, ni par fichier, ni par
`eslint-disable`) pour faire passer le lint. Si une règle semble se tromper,
on adapte le code ; si elle se trompe réellement, on en discute dans la PR
avant de toucher à `eslint.config.js`.

## 1. Où vit un composant ?

| Cas                                                        | Emplacement                                      |
| ---------------------------------------------------------- | ------------------------------------------------ |
| Réutilisable par plusieurs apps AMANA (Modal, Toast, etc.) | Dépôt `amana_shared_ui`, importé depuis `@amana/shared-ui` |
| Spécifique à AMANA Planning                                | `resources/js/components/<domaine>/`             |
| Spécifique à une page Inertia                              | `resources/js/Pages/<Section>/`                  |

- On importe **toujours** les composants et composables partagés depuis
  `@amana/shared-ui` (`import { Modal, useToast } from "@amana/shared-ui"`),
  jamais depuis une copie locale.
- **Quand un composant est promu vers `amana_shared_ui`, la copie locale est
  supprimée dans la même PR** (avec ses composables et helpers devenus
  orphelins). Une copie locale qui survit diverge silencieusement du composant
  partagé et devient du code mort. `components/shared/` ne contient que ce qui
  est propre à cette app.
- Pas de code mort : variable, `ref`, import, fichier ou composable inutilisé
  → on le supprime (`vue-tsc --noEmit --noUnusedLocals` aide à les repérer).

## 2. Montage d'îlots Vue sur des pages Blade (`app.ts`)

`app.ts` est le point d'entrée : il **monte** des composants, il n'en
**définit** pas.

- Un seul composant par fichier (`vue/one-component-per-file`) :
  pas d'objet `{ data, render, setup… }` inline passé à `createApp()`.
- Les données viennent des `data-*` Blade et sont transmises en **props
  racine** : `createApp(MonComposant, rootProps).mount(el)`. Pas besoin de
  `h()` ni de wrapper `render`.
- Les props racine sont déclarées dans une constante (`const rootProps = {…}`)
  avant l'appel. C'est plus lisible, et c'est nécessaire : la règle
  `vue/one-component-per-file` prend à tort un littéral d'objet passé en
  second argument de `createApp()` pour une définition de composant.
- Si l'îlot a besoin d'un état local (ex. un composant contrôlé en `v-model`
  sans parent), on crée un petit composant SFC dédié, suffixé `Island`
  (exemple : `components/shared/SearchableSelectIsland.vue`). Son en-tête
  explique pourquoi il existe.
- Montage d'un seul élément par id : `mountIfPresent("vue-xxx", Composant)`.
  Plusieurs éléments par page : `querySelectorAll(...).forEach(...)`.
- Les props `undefined` retombent sur les valeurs par défaut du composant
  (`withDefaults`) : pour « pas de valeur », passer `undefined`, pas `""`.

## 3. Pages Inertia (`resources/js/Pages/`)

- Les pages sont résolues **par chemin** (`import.meta.glob("./Pages/**/*.vue")`
  dans `app.ts`), et la règle `vue/multi-word-component-names` reste active
  pour elles : le nom du fichier doit comporter **au moins deux mots**.
- Convention : `Pages/<Section>/<Section><Action>.vue`, en PascalCase.
  Exemples : `Pages/Guide/GuideIndex.vue`, `Pages/Personnes/PersonnesIndex.vue`,
  `Pages/Personnes/PersonnesEdit.vue`.
- Le nom Inertia côté contrôleur est le chemin sans extension :
  `Inertia::render('Guide/GuideIndex', [...])`. Renommer une page, c'est
  renommer le fichier **et** cet appel (et les commentaires qui y renvoient).
- Avantage : les noms de composants restent distincts dans Vue Devtools et
  dans les traces d'erreur (au lieu d'une pile de `Index`).

## 4. Style de code qui évite les erreurs de lint courantes

- Pas d'expression utilisée comme instruction (`@typescript-eslint/no-unused-expressions`).
  Un ternaire n'est pas un `if` :

    ```ts
    // ✗
    set.has(x) ? set.delete(x) : set.add(x);
    // ✓
    if (set.has(x)) {
        set.delete(x);
    } else {
        set.add(x);
    }
    ```

- Pas de variable, `ref` ou import déclaré mais inutilisé
  (`@typescript-eslint/no-unused-vars`). Un « compteur pour forcer la mise à
  jour » jamais lu ne force rien.
- Ordre des attributs dans les templates (`vue/attributes-order`) : ordre
  recommandé par `eslint-plugin-vue` (directives de structure, puis `v-model`,
  puis attributs ordinaires, puis `@événements` en dernier). Correction
  automatique : `npx eslint --fix resources/js`.

## 5. Mise en forme (Prettier)

La mise en forme est déléguée à **Prettier** ; ESLint ne s'en occupe pas.

- `npm run format` corrige les fichiers de `resources/js` en place.
- `npm run format:check` vérifie sans rien modifier (c'est ce que lance la CI).
- Réglages dans `.prettierrc.json` : largeur 120, guillemets doubles, points-virgules,
  virgules finales. L'indentation (4 espaces) et les fins de ligne (LF) viennent de
  `.editorconfig`, que Prettier lit automatiquement : ne pas les dupliquer.
- `eslint-config-prettier` (dernier élément de `eslint.config.js`) désactive les
  règles ESLint qui entreraient en conflit avec Prettier. Ne pas ajouter de règle
  ESLint de style (indentation, guillemets, longueur de ligne…) : c'est le rôle de
  Prettier.
- Un reformatage de masse se fait dans un commit dédié, listé dans
  `.git-blame-ignore-revs` (procédure : [backend-conventions.md](backend-conventions.md) §3).

## 6. Avant d'ouvrir une PR touchant `resources/js/`

```bash
npm run format:check # Prettier ; corriger avec `npm run format`
npm run lint         # doit afficher 0 erreur ET 0 avertissement
npm run type-check   # vue-tsc --noEmit
npm run build        # vite build
```

La CI échoue sur les erreurs de lint ; les avertissements ne la font pas
échouer mais ne doivent pas être introduits non plus.
