/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        // Blade — inchangé
        './resources/views/**/*.blade.php',
        // JS/TS — on étend pour inclure TypeScript et les Single File Components Vue
        './resources/js/**/*.{js,ts,vue}',
    ],

    theme: {
        extend: {
            colors: {
                accent: {
                    DEFAULT: '#0369a1',
                    dark:    '#0284c7',
                    light:   '#0ea5e9',
                },
                // surface/ink sont pilotées par des variables CSS (voir
                // resources/css/app.css, blocs :root et .dark) plutôt que
                // des valeurs hexadécimales fixes, afin qu'un simple ajout
                // de la classe .dark sur <html> retheme toute l'app sans
                // avoir à toucher aux composants qui utilisent déjà ces
                // classes (bg-surface, text-ink, border-surface-border…).
                surface: {
                    DEFAULT: 'rgb(var(--color-surface) / <alpha-value>)',
                    2:       'rgb(var(--color-surface-2) / <alpha-value>)',
                    3:       'rgb(var(--color-surface-3) / <alpha-value>)',
                    border:  'rgb(var(--color-surface-border) / <alpha-value>)',
                },
                ink: {
                    DEFAULT: 'rgb(var(--color-ink) / <alpha-value>)',
                    light:   'rgb(var(--color-ink-light) / <alpha-value>)',
                    muted:   'rgb(var(--color-ink-muted) / <alpha-value>)',
                    faint:   'rgb(var(--color-ink-faint) / <alpha-value>)',
                },
                // La sidebar reste volontairement toujours sombre, qu'on
                // soit en mode clair ou sombre — c'est une barre de
                // navigation à identité fixe, pas une "surface" de contenu.
                sidebar: {
                    DEFAULT: '#0c1e2e',
                    2:       '#0f2740',
                },
            },

            fontFamily: {
                body:    ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                heading: ['"Sora"', 'system-ui', 'sans-serif'],
            },

            borderRadius: {
                sm:      '6px',
                DEFAULT: '10px',
                lg:      '14px',
                xl:      '20px',
            },

            boxShadow: {
                sm:      '0 1px 3px rgba(13,17,23,0.07), 0 1px 2px rgba(13,17,23,0.04)',
                DEFAULT: '0 4px 12px rgba(13,17,23,0.08), 0 2px 4px rgba(13,17,23,0.05)',
                lg:      '0 12px 32px rgba(13,17,23,0.12), 0 4px 8px rgba(13,17,23,0.06)',
                glow:    '0 0 0 3px rgba(3,105,161,0.25)',
            },

            spacing: {
                sidebar: 'var(--sidebar-width)',
                topbar:  '56px',
            },

            width: {
                sidebar: 'var(--sidebar-width)',
            },

            height: {
                topbar: '56px',
            },

            minHeight: {
                topbar: '56px',
            },
        },
    },

    plugins: [
        require('@tailwindcss/forms'),
    ],
};
