/** @type {import('tailwindcss').Config} */
import amanaPreset from '@amana/shared-ui/tailwind-preset';

export default {
    presets: [amanaPreset],
    darkMode: 'class',

    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,vue}',
        './node_modules/@amana/shared-ui/src/**/*.{js,ts,vue}',
        // CRITIQUE : sans cette ligne, Tailwind ne scanne jamais les vues
        // Blade du package partagé (login, sidebar, layout principal,
        // paramètres/journal/activité génériques) — leurs classes ne sont
        // alors jamais générées dans le CSS compilé, même si le HTML les
        // référence correctement. Symptômes observés sans cette ligne :
        // padding du layout principal disparu, panneau gauche de la page
        // de connexion cassé, badge de rôle sans style, sidebar dont les
        // classes structurelles (position, largeur) manquent.
        './vendor/amana/shared/resources/views/**/*.blade.php',
        // Filet de sécurité pour le développement local avec
        // composer.local.json (voir docs/local-development.md) : si jamais
        // le lien symbolique vendor/amana/shared n'est pas suivi
        // correctement par le scanner de contenu, ce chemin direct vers le
        // dossier frère fonctionne quel que soit le mode d'installation.
        // Ignoré silencieusement (aucune erreur) s'il n'existe pas, comme
        // sur un serveur où amana/shared est installé via le dépôt git.
        '../amana_shared/resources/views/**/*.blade.php',
    ],

    plugins: [
        require('@tailwindcss/forms'),
    ],
};
