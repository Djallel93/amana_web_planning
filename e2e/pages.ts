// e2e/pages.ts
// Inventaire des pages testées (routes GET de routes/web.php, 14/10/2026).
// `roles` = rôles qui doivent y accéder. À CONFIRMER après le premier passage
// (hiérarchie admin > gestionnaire > membre ; statut du rôle « benevole » à vérifier).

import type { Role } from './fixtures';

export interface PageSpec {
    slug: string;
    path: string;
    roles: Role[];
}

const ALL: Role[] = ['admin', 'gestionnaire', 'membre', 'benevole'];
const MEMBRE_PLUS: Role[] = ['admin', 'gestionnaire', 'membre'];
const GEST_PLUS: Role[] = ['admin', 'gestionnaire'];
const ADMIN: Role[] = ['admin'];

export const PUBLIC_PAGES = [
    { slug: 'login', path: '/login' },
    { slug: 'mot-de-passe-oublie', path: '/mot-de-passe-oublie' },
    { slug: 'inscription', path: '/inscription' },
];

export const AUTH_PAGES: PageSpec[] = [
    { slug: 'mon-planning', path: '/mon-planning', roles: ALL },
    { slug: 'guide', path: '/guide', roles: ALL },
    { slug: 'mon-profil', path: '/mon-profil', roles: ALL },
    { slug: 'echanges', path: '/echanges', roles: ALL },
    { slug: 'planning', path: '/planning', roles: ALL },
    { slug: 'planning-stats', path: '/planning/stats', roles: ALL },
    { slug: 'planning-export', path: '/planning/export', roles: ALL },
    { slug: 'restrictions', path: '/restrictions', roles: ALL },
    { slug: 'absences', path: '/absences', roles: ALL },
    { slug: 'evenements', path: '/evenements', roles: ALL },
    { slug: 'bilan', path: '/bilan', roles: MEMBRE_PLUS },
    { slug: 'bilan-statistiques', path: '/bilan/statistiques', roles: MEMBRE_PLUS },
    { slug: 'planning-generer', path: '/planning/generer', roles: GEST_PLUS },
    { slug: 'parametres', path: '/parametres', roles: GEST_PLUS },
    { slug: 'evenements-creer', path: '/evenements/creer', roles: GEST_PLUS },
    { slug: 'evenements-import', path: '/evenements/import', roles: GEST_PLUS },
    { slug: 'admin-echanges', path: '/admin/echanges', roles: GEST_PLUS },
    { slug: 'personnes', path: '/personnes', roles: ADMIN },
    { slug: 'personnes-creer', path: '/personnes/creer', roles: ADMIN },
    { slug: 'admin-candidatures', path: '/admin/candidatures', roles: ADMIN },
    { slug: 'admin-journal', path: '/admin/journal', roles: ADMIN },
    { slug: 'admin-activite', path: '/admin/activite', roles: ADMIN },
];
