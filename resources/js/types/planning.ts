// resources/js/types/planning.ts
//
// Types partagés pour le module Planning — reflètent exactement le JSON
// renvoyé par GET /planning/data (voir PlanningApiController::data()).
//
// Garder ce fichier synchronisé avec le contrôleur PHP si la forme du JSON
// change. C'est le seul endroit où la "forme des données planning" est
// déclarée côté TypeScript — tous les composants planning l'importent d'ici.

export interface PersonneAssignee {
    id: number;
    label: string;
}

// Les 5 codes de tâches actifs dans l'app — utilisé pour garantir l'ordre
// d'affichage des colonnes (entree, mektaba, salle, amana_food, cours).
export type TacheCode = "entree" | "mektaba" | "salle" | "amana_food" | "cours";

export interface TacheAssignation {
    code: TacheCode;
    tacheId: number | null;
    bloquee: boolean;
    evenementBloquant: string | null;
    personne: PersonneAssignee | null;
}

export interface CreneauData {
    id: number;
    date: string; // "2025-03-14" (ISO)
    dateLabel: string; // "14 mars 2025"
    jour: string; // "Vendredi"
    toutBloque: boolean;
    partielBloque: boolean;
    evenements: string | null;
    taches: TacheAssignation[];
}

export interface BanniereEvenement {
    nom: string;
    dateLabel: string;
    informatif: boolean;
    tachesBloquees: { code: string; libelle: string }[];
}

export interface SemaineData {
    cle: string;
    numeroSemaine: number;
    anneeAffichage: number;
    moisAffichage: number;
    libelleSemaine: string;
    lundi: string;
    dimanche: string;
    datesExistantes: string[];
    evenementBloquantTotal: string | null;
    bannieres: BanniereEvenement[];
    creneaux: CreneauData[];
}

export interface PlanningResponse {
    semaines: SemaineData[];
    historique: boolean;
    peutEditer: boolean;
}

// ── Contexte transmis à AssignModal lors de l'ouverture ───────────────────
export interface AssignContext {
    creneauId: number;
    tacheId: number | null;
    tacheCode: TacheCode;
    tacheLabel: string;
    jour: string;
    dateLabel: string;
    currentPersonneId: number | null;
}

// ── Contexte transmis à AddCreneauModal lors de l'ouverture ───────────────
export interface AddCreneauContext {
    weekMin: string; // lundi de la semaine, ISO
    weekMax: string; // dimanche de la semaine, ISO
    existingDates: string[];
}

// ── Métadonnées d'affichage des 5 tâches (libellé + couleur) ──────────────
// Équivalent TS du tableau $tachesMeta défini en PHP dans _week-block.blade.php.
export const TACHES_META: Record<
    TacheCode,
    { label: string; colorClass: string }
> = {
    entree: { label: "🚪 Entrée", colorClass: "text-[#2563eb]" },
    mektaba: { label: "📚 Mektaba", colorClass: "text-[#059669]" },
    salle: { label: "🏛️ Salle", colorClass: "text-[#d97706]" },
    amana_food: { label: "🥪 Amana Food", colorClass: "text-[#e11d48]" },
    cours: { label: "🎓 Cours", colorClass: "text-[#7c3aed]" },
};

export const TACHE_CODES: TacheCode[] = [
    "entree",
    "mektaba",
    "salle",
    "amana_food",
    "cours",
];

// ── window.PlanningConfig ──────────────────────────────────────────────────
// Déclaration globale UNIQUE — injectée par planning/index.blade.php et
// mon-planning.blade.php au chargement de page (voir window.PlanningConfig
// dans le @push('scripts') de chaque vue).
//
// Pourquoi ici et pas dans chaque composant qui l'utilise ?
// TypeScript exige qu'une interface globale (declare global) soit déclarée
// de façon identique partout où elle apparaît — sinon "Subsequent property
// declarations must have the same type". En la centralisant dans ce fichier
// partagé, AssignModal.vue, AddCreneauModal.vue et PlanningGrid.vue
// n'ont qu'à l'importer (ou juste importer ce fichier une fois, ce qui
// suffit à enregistrer la déclaration globale pour tout le bundle).
declare global {
    interface Window {
        PlanningConfig: {
            csrf: string;
            routes: {
                personnes: string;
                assignation: string;
                creneau: string;
                data: string;
                annulationCours: string;
            };
        };
    }
}
