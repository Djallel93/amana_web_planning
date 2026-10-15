// vite.config.ts
// Point de configuration Vite pour AMANA Planning.
// Compile à la fois le CSS (Tailwind) et le JS/TS (Vue 3).

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        // Plugin Vue — transforme les fichiers .vue en JS compréhensible par le navigateur.
        // Il doit être déclaré AVANT le plugin Laravel.
        vue(),

        // Tailwind 4 (14/10/2026) : plugin Vite, remplace PostCSS + autoprefixer.
        tailwindcss(),

        laravel({
            // On ajoute resources/js/app.ts à côté du CSS existant.
            // Vite produira deux fichiers dans public/build/assets/ :
            //   app-[hash].css  (Tailwind)
            //   app-[hash].js   (Vue + nos composants)
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
            ],
            refresh: true,
        }),
    ],

    // Serveur de dev (Docker, 14/10/2026) : écoute sur toutes les interfaces
    // pour être joignable depuis l'hôte ; le port vient de VITE_PORT (5174 par
    // défaut dans docker-compose.yml, 5173 hors Docker).
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: { host: 'localhost', clientPort: Number(process.env.VITE_PORT ?? 5173) },
    },

    resolve: {
        alias: {
            // Permet d'importer depuis "@/components/..." au lieu de
            // "../../components/..." — plus lisible dans les fichiers profonds.
            '@': '/resources/js',
        },
    },
});
