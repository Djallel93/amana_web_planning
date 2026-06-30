// resources/js/composables/useModal.ts
//
// Composable générique pour ouvrir/fermer un modal.
//
// ── Pourquoi "générique" ? ────────────────────────────────────────────────
// Chaque modal peut avoir besoin de stocker des données différentes :
// l'AssignModal a besoin d'un créneau + tâche, le SwapModal d'un autre objet.
// Plutôt qu'un composable par modal, on utilise un générique TypeScript : T.
// T est un "paramètre de type" — comme un paramètre de fonction, mais pour
// les types. useModal<Creneau>() retourne un state typé Creneau | null.
// useModal<SwapTarget>() retourne un state typé SwapTarget | null.
// Un seul composable, zéro duplication.
//
// ── Pourquoi PAS module-level ici (contrairement à useToast) ? ────────────
// useToast avait un état global partagé (une seule liste de toasts pour toute
// l'app). useModal est l'inverse : chaque modal doit avoir son PROPRE état
// isOpen / data — sinon ouvrir l'AssignModal fermerait le SwapModal.
// On déclare donc les refs À L'INTÉRIEUR de la fonction, ce qui crée une
// instance indépendante à chaque appel.

import { ref, readonly } from 'vue';

// ── Générique T ───────────────────────────────────────────────────────────
// La syntaxe <T = null> signifie : "T est un type libre, avec null par défaut
// si on n'en spécifie pas". Chaque appelant peut passer ce qu'il veut :
//   const modal = useModal<{ creneauId: number; tacheLabel: string }>();

export function useModal<T = null>() {

    // ref<boolean> : TS infère le type depuis la valeur initiale (false → boolean).
    const isOpen = ref(false);

    // T | null : le modal peut contenir des données de type T, ou rien (null).
    // Utile pour passer le contexte au moment d'ouvrir ("quel créneau ?").
    const data = ref<T | null>(null);

    function open(payload?: T): void {
        data.value = payload ?? null;
        isOpen.value = true;
        // Bloquer le scroll de la page pendant que le modal est ouvert —
        // même comportement que dans le JS vanilla actuel.
        document.body.style.overflow = 'hidden';
    }

    function close(): void {
        isOpen.value = false;
        data.value = null;
        document.body.style.overflow = '';
    }

    // readonly() : expose isOpen en lecture seule vers l'extérieur.
    // Le composant parent peut lire isOpen.value mais ne peut pas faire
    // isOpen.value = true directement — il doit passer par open().
    // Cela rend le flux de données prévisible (unidirectionnel).
    return { isOpen: readonly(isOpen), data: readonly(data), open, close };
}
