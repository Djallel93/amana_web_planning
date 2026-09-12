// eslint.config.js
//
// Configuration ESLint (flat config, ESLint 9+) pour le frontend Vue 3 /
// TypeScript de AMANA Planning. Objectif : attraper de vraies erreurs et
// les pièges classiques de Vue, pas imposer un style (Airbnb, Standard…)
// différent de l'existant.
//
// ── Pourquoi pas de règles de style (indent/quotes/semi) ────────────────
// Le dépôt a déjà .editorconfig (4 espaces, LF, UTF-8) comme source de
// vérité pour l'indentation — activer en plus des règles ESLint
// stylistiques dupliquerait ce rôle et risquerait d'entrer en conflit
// (ESLint et .editorconfig ne se parlent pas). On choisit .editorconfig
// comme seule source de vérité pour le formatage ; ESLint se concentre
// sur les bugs et les anti-patterns Vue/TS.
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
import js from "@eslint/js";
import pluginVue from "eslint-plugin-vue";
import tseslint from "typescript-eslint";
import vueParser from "vue-eslint-parser";
import globals from "globals";

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
        rules: {
            // ── Désactivées : mise en forme, pas des bugs ───────────────
            // pluginVue.configs["flat/recommended"] active par défaut
            // plusieurs règles purement cosmétiques dont le réglage par
            // défaut (indentation 2 espaces) contredit .editorconfig
            // (4 espaces, déjà en place dans tout le code existant). Comme
            // documenté en tête de fichier, .editorconfig reste la seule
            // source de vérité pour le formatage — on désactive ces
            // règles plutôt que de les reconfigurer en double, pour ne
            // pas avoir deux systèmes à maintenir en synchronisation.
            "vue/html-indent": "off",
            "vue/html-closing-bracket-newline": "off",
            "vue/max-attributes-per-line": "off",
            "vue/html-self-closing": "off",
            "vue/attributes-order": "off",
            "vue/singleline-html-element-content-newline": "off",
            "vue/multiline-html-element-content-newline": "off",
            "vue/first-attribute-linebreak": "off",
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
);
