// resources/js/lib/theme.ts
//
// Bascule mode clair / mode sombre, persistée dans localStorage.
//
// Le script anti-flash inline dans layouts/partials/head.blade.php applique
// déjà la bonne classe sur <html> AVANT que ce module ne charge (pour éviter
// un flash de thème clair au premier rendu si l'utilisateur préfère le mode
// sombre). Ce module ne fait que piloter le bouton bascule dans la sidebar
// et garder localStorage synchronisé après un clic.
//
// Toutes les couleurs de l'app (bg-surface, text-ink, border-surface-border…)
// suivent automatiquement via les variables CSS définies dans
// resources/css/app.css (:root / .dark) — aucune logique par composant.

const STORAGE_KEY = 'amana-theme';

export type Theme = 'light' | 'dark';

export function getCurrentTheme(): Theme {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

export function applyTheme(theme: Theme): void {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    localStorage.setItem(STORAGE_KEY, theme);
}

export function toggleTheme(): Theme {
    const next: Theme = getCurrentTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    return next;
}

// ── Pont pour le bouton dans sidebar.blade.php ─────────────────────────────
// Même pattern que window.closeSidebar dans MobileSidebar.vue : la sidebar
// reste du Blade classique, ce petit pont évite d'y introduire un composant
// Vue entier pour un simple bouton bascule.
declare global {
    interface Window {
        toggleAppTheme: () => void;
    }
}

export function registerThemeToggle(): void {
    window.toggleAppTheme = () => {
        const theme = toggleTheme();
        document.querySelectorAll<HTMLElement>('[data-theme-icon]').forEach((el) => {
            el.textContent = theme === 'dark' ? '☀️' : '🌙';
        });
    };

    // Initialise l'icône du bouton à l'état déjà appliqué par le script
    // anti-flash, au cas où elle serait rendue avant que ce module ne charge.
    document.querySelectorAll<HTMLElement>('[data-theme-icon]').forEach((el) => {
        el.textContent = getCurrentTheme() === 'dark' ? '☀️' : '🌙';
    });
}
