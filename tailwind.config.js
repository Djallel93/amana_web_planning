/** @type {import('tailwindcss').Config} */
export default {
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
                surface: {
                    DEFAULT: '#ffffff',
                    2:       '#f8f9fb',
                    3:       '#f0f2f5',
                    border:  '#e5e7eb',
                },
                ink: {
                    DEFAULT: '#0d1117',
                    light:   '#374151',
                    muted:   '#6b7280',
                    faint:   '#d1d5db',
                },
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
