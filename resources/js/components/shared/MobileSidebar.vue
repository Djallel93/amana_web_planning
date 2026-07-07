<!-- resources/js/components/shared/MobileSidebar.vue -->
<!--
    Remplace le script inline "Script sidebar mobile" de layouts/app.blade.php.
    Gère deux comportements distincts sur le même état isOpen :
      1. Mobile (< 640px) : sidebar masquée par défaut, ouverte en overlay
         par-dessus le contenu via le hamburger (bouton existant en haut).
      2. Desktop (>= 640px) : sidebar visible par défaut, peut être repliée
         (collapse) via un nouveau bouton rond flottant ancré au bord gauche
         de l'écran, qui suit la sidebar quand elle coulisse.

    ── Pourquoi un seul état isOpen pour les deux cas ? ───────────────────
    Mobile "fermé" et desktop "collapsed" sont visuellement la même chose :
    sidebar translatée hors écran (-100%), mainWrapper sans marge gauche.
    Le markup CSS existant (.sidebar-hidden, transform: translateX(-100%))
    fonctionne déjà pour les deux tailles d'écran — pas besoin de dupliquer
    la logique, juste de réutiliser la même classe sur les deux breakpoints.

    ── Pont DOM ──────────────────────────────────────────────────────────
    Comme HoraireSettings/EventTaskBlocker : ce composant ne rend PAS le
    markup de la sidebar (qui reste dans sidebar.blade.php, riche en
    permissions Blade) — il pilote les classes CSS du DOM existant et
    rend uniquement le nouveau bouton collapse, absent du HTML Blade.
-->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

// ── État ──────────────────────────────────────────────────────────────────
// true = sidebar visible (qu'elle soit "ouverte" sur mobile ou "expanded"
// sur desktop — même état, deux interprétations selon la largeur d'écran).
// Toujours true au chargement (pas de persistance — confirmé avec Djallel).
const isOpen = ref(true);

function isMobile(): boolean {
    return window.innerWidth < 640;
}

// ── Références DOM ──────────────────────────────────────────────────────
// Comme dans EventTaskBlocker/HoraireSettings : on récupère les éléments
// existants du DOM Blade au montage plutôt que de les re-rendre en Vue.
let sidebar: HTMLElement | null = null;
let overlay: HTMLElement | null = null;
let hamburger: HTMLElement | null = null;
let mainWrapper: HTMLElement | null = null;

// Largeur réelle de la sidebar (source de vérité : le DOM, pas une constante
// dupliquée). --sidebar-width est fluide (clamp() défini dans custom.css) et
// peut donc changer avec la largeur de l'écran ; on lit le rendu effectif via
// getBoundingClientRect() plutôt que de reparser la variable CSS, pour rester
// correct même si la formule du clamp() change un jour.
const sidebarWidthPx = ref('252px');

function readSidebarWidth(): void {
    if (sidebar) sidebarWidthPx.value = `${sidebar.getBoundingClientRect().width}px`;
}

function applyDom(): void {
    if (!sidebar || !overlay || !hamburger || !mainWrapper) return;

    if (isOpen.value) {
        sidebar.classList.remove('sidebar-hidden');
        // Sur desktop, sidebar-hidden n'a aucun effet visuel (la règle CSS
        // .sidebar-hidden dans custom.css n'est définie que sous
        // @media (max-width: 639px)). Le repli desktop doit donc être piloté
        // explicitement via un transform inline, indépendant de cette classe.
        sidebar.style.transform = '';
        hamburger.setAttribute('aria-expanded', 'true');
        // L'overlay et le scroll-lock ne concernent que le mode mobile —
        // sur desktop la sidebar partage l'écran avec le contenu, pas besoin
        // d'assombrir ni de bloquer le scroll.
        if (isMobile()) {
            sidebar.classList.add('shadow-lg');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100');
            hamburger.classList.add('hamburger-open');
            document.body.style.overflow = 'hidden';
            // Sur mobile, la sidebar est en overlay — le contenu ne se décale jamais.
            mainWrapper.style.marginLeft = '0px';
        } else {
            readSidebarWidth();
            mainWrapper.style.marginLeft = sidebarWidthPx.value;
        }
    } else {
        sidebar.classList.add('sidebar-hidden');
        sidebar.classList.remove('shadow-lg');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100');
        hamburger.classList.remove('hamburger-open');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        mainWrapper.style.marginLeft = '0px';
        // Repli desktop explicite (voir commentaire ci-dessus). Sur mobile,
        // la classe sidebar-hidden suffit déjà — on nettoie le transform
        // inline pour ne pas laisser une valeur desktop résiduelle après un
        // redimensionnement de fenêtre (ex: passage desktop → mobile).
        sidebar.style.transform = isMobile() ? '' : 'translateX(-100%)';
    }
}

// ── Actions ───────────────────────────────────────────────────────────────
function open(): void {
    isOpen.value = true;
    applyDom();
}

// Fermeture "implicite" — déclenchée par l'overlay ou un clic sur un lien nav
// (onclick="closeSidebar()" dans sidebar.blade.php). Ne doit fermer que sur
// mobile : sur desktop, naviguer ne doit jamais replier la sidebar, seul le
// bouton collapse dédié le fait (sinon chaque clic nav la replierait, ce qui
// serait très gênant en usage desktop).
function closeOnNavigate(): void {
    if (!isMobile()) return;
    isOpen.value = false;
    applyDom();
}

// Fermeture/repli explicite — utilisée par le bouton collapse desktop ET
// par le hamburger mobile (toggle), donc sans la garde isMobile() ci-dessus.
function close(): void {
    isOpen.value = false;
    applyDom();
}

function toggle(): void {
    isOpen.value ? close() : open();
}

// ── Listeners ─────────────────────────────────────────────────────────────
function onKeydown(e: KeyboardEvent): void {
    // Escape ferme la sidebar mobile (overlay) ; sur desktop elle ne doit
    // rien faire — seul le bouton collapse dédié replie la sidebar.
    if (e.key === 'Escape') closeOnNavigate();
}

function onResize(): void {
    // En passant desktop → mobile ou l'inverse, on resynchronise le DOM
    // (classes shadow/overlay diffèrent selon le mode) sans changer isOpen,
    // sauf si on vient de franchir le seuil alors que la sidebar était
    // explicitement fermée sur mobile — dans ce cas on laisse l'état tel quel,
    // c'est l'utilisateur qui rouvrira via le bouton approprié.
    // On relit aussi la largeur réelle : --sidebar-width est fluide (clamp()
    // basé sur vw), elle peut donc changer à chaque redimensionnement.
    readSidebarWidth();
    applyDom();
}

onMounted(() => {
    sidebar     = document.getElementById('mainSidebar');
    overlay     = document.getElementById('sidebarOverlay');
    hamburger   = document.getElementById('hamburgerBtn');
    mainWrapper = document.getElementById('mainWrapper');

    // Sur mobile, l'état initial est "fermé" (sidebar cachée par défaut,
    // comme avant). On ne lit window.innerWidth qu'ici, au montage, jamais
    // au moment de la déclaration du ref (au cas où ce composant serait un
    // jour rendu dans un contexte sans `window` disponible).
    if (isMobile()) isOpen.value = false;

    readSidebarWidth();

    hamburger?.addEventListener('click', toggle);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);

    // Le onclick="closeSidebar()" inline sur l'overlay (dans sidebar.blade.php)
    // reste fonctionnel car on expose closeSidebar() sur window ci-dessous —
    // pont temporaire, comme pour les autres composants déjà convertis.
    applyDom();
});

onUnmounted(() => {
    hamburger?.removeEventListener('click', toggle);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
});

// Pont pour onclick="closeSidebar()" dans sidebar.blade.php (overlay + liens nav).
// Utilise closeOnNavigate() (garde mobile uniquement) — voir commentaire plus haut.
declare global {
    interface Window {
        closeSidebar: () => void;
    }
}
window.closeSidebar = closeOnNavigate;

// ── Classe du bouton collapse ──────────────────────────────────────────────
// Le bouton est fixed, ancré verticalement au milieu de l'écran (top: 50%),
// et translaté horizontalement selon l'état de la sidebar :
//   - sidebar ouverte  → bouton collé à la bordure droite de la sidebar
//                        (left: largeur réelle de la sidebar, fluide)
//   - sidebar fermée   → bouton collé au bord gauche de l'écran (left: 0)
// La transition est portée par la même durée que celle de la sidebar
// (.3s cubic-bezier, déjà défini dans custom.css pour #mainSidebar) pour
// qu'ils se déplacent ensemble visuellement.
const collapseButtonStyle = computed(() => ({
    left: isOpen.value ? sidebarWidthPx.value : '0px',
}));
</script>

<template>
    <!--
        Bouton collapse — visible uniquement sur desktop (sm:flex, hidden sur mobile
        où le hamburger existant suffit). Position fixed + transition sur `left`
        pour suivre le bord de la sidebar quand elle coulisse.
    -->
    <button
        class="hidden sm:flex fixed top-1/2 -translate-y-1/2 z-[250] w-6 h-12 items-center justify-center
               bg-sidebar-2 hover:bg-accent border border-white/10 rounded-r-md text-white/50 hover:text-white
               transition-[left,background-color,color] duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] cursor-pointer
               shadow-md"
        :style="collapseButtonStyle"
        @click="toggle"
        :aria-expanded="isOpen"
        :aria-label="isOpen ? 'Réduire la barre latérale' : 'Afficher la barre latérale'"
    >
        <span class="text-[11px] leading-none transition-transform duration-300" :class="isOpen ? '' : 'rotate-180'">
            ◀
        </span>
    </button>
</template>
