// eslint.config.js
//
// Configuration ESLint (flat config, ESLint 9+) pour le frontend Vue 3 /
// TypeScript de AMANA Planning. Objectif : attraper de vraies erreurs et
// les pièges classiques de Vue, pas imposer un style (Airbnb, Standard…)
// différent de l'existant.
//
// ── Pas de règles de style dans ESLint : Prettier s'en charge ───────────
// La mise en forme (indentation, guillemets, longueur de ligne, retours à la
// ligne des templates…) est déléguée à Prettier (.prettierrc.json ; il lit
// aussi .editorconfig pour l'indentation et les fins de ligne). ESLint ne
// garde que les bugs et les anti-patterns Vue/TS. `eslint-config-prettier`,
// placé en dernier dans la configuration, désactive les règles ESLint qui
// entreraient en conflit avec Prettier : pas besoin de les lister à la main.
//
// ── Pourquoi "flat/recommended" et pas "flat/strict" ─────────────────────
// Ce dépôt n'a aucun historique de lint : partir de "strict" ferait
// remonter un volume de signalements disproportionné dès le premier run.
// "recommended" est un point de départ raisonnable, à durcir plus tard une
// fois que l'équipe s'est appropriée l'outil.
//
// ── typescript-eslint non type-aware pour l'instant ──────────────────────
// Le linting "type-checked" (qui utilise le compilateur TS pour des règles
// plus poussées, ex. no-unsafe-assignment) est plus lent (il type-check
// vraiment le projet) et plus strict. On reste sur le recommended non
// type-checked pour garder `npm run lint` rapide. Pour l'activer plus
// tard, voir https://typescript-eslint.io/getting-started/typed-linting/
// (remplacer tseslint.configs.recommended par
// tseslint.configs.recommendedTypeChecked et ajouter
// languageOptions.parserOptions.project).
//
// ── Pas de règle coupée pour « faire passer » le lint ────────────────────
// Aucune règle n'est désactivée à la main (ni ici, ni par eslint-disable) :
// on corrige le code. Seules les règles de mise en forme en conflit avec
// Prettier sont coupées, automatiquement, par eslint-config-prettier.
// Conventions et cas pratiques : docs/frontend-conventions.md.
import js from "@eslint/js";
import pluginVue from "eslint-plugin-vue";
import tseslint from "typescript-eslint";
import vueParser from "vue-eslint-parser";
import globals from "globals";
import eslintConfigPrettier from "eslint-config-prettier/flat";

export default tseslint.config(
    {
        ignores: ["node_modules", "public/build", "vendor"],
    },

    js.configs.recommended,
    ...pluginVue.configs["flat/recommended"],
    ...tseslint.configs.recommended,

    {
        files: ["resources/js/**/*.{ts,vue}"],
        languageOptions: {
            ecmaVersion: "latest",
            sourceType: "module",
            globals: {
                ...globals.browser,
            },
        },
    },

    {
        // Délègue l'analyse des blocs <script>/<script setup> des .vue au
        // parser TypeScript, sinon vue-eslint-parser traite leur contenu
        // comme du JS et typescript-eslint ne s'applique pas dedans.
        //
        // On doit re-préciser explicitement `parser: vueParser` ici (pas
        // seulement `parserOptions.parser`) : tseslint.configs.recommended
        // contient une entrée sans restriction de `files` qui positionne
        // le parser TypeScript globalement, y compris pour les .vue —
        // sans ce bloc, ce réglage global gagnerait sur celui de
        // pluginVue.configs["flat/recommended"] et casserait le parsing
        // du <template>.
        files: ["resources/js/**/*.vue"],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
            },
        },
    },

    eslintConfigPrettier,
);
