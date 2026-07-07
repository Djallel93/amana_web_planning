// vite.config.ts
// Point de configuration Vite pour AMANA Planning.
// Compile à la fois le CSS (Tailwind) et le JS/TS (Vue 3).

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        // Plugin Vue — transforme les fichiers .vue en JS compréhensible par le navigateur.
        // Il doit être déclaré AVANT le plugin Laravel.
        vue(),

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

    resolve: {
        alias: {
            // Permet d'importer depuis "@/components/..." au lieu de
            // "../../components/..." — plus lisible dans les fichiers profonds.
            '@': '/resources/js',
        },
    },
});
