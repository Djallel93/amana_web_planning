// resources/js/composables/useToast.ts
//
// Composable Vue pour les notifications toast.
//
// ── Qu'est-ce qu'un composable ? ──────────────────────────────────────────
// Un composable est une fonction ordinaire qui utilise les APIs réactives de
// Vue (ref, computed, watch…) et encapsule une logique réutilisable.
// Convention : le nom commence par "use" (useToast, useModal, useFetch…).
// On l'importe et on l'appelle depuis n'importe quel composant.
//
// ── Pourquoi "shared state" avec module-level ref ? ───────────────────────
// Si on déclarait `toasts` à l'intérieur de la fonction useToast(), chaque
// appel à useToast() aurait SA PROPRE liste de toasts — les composants ne
// partageraient pas le même état.
// En déclarant `toasts` AU NIVEAU DU MODULE (ici, hors de la fonction),
// il n'existe qu'une seule instance pour toute l'application, même si
// useToast() est appelé depuis plusieurs composants différents.

import { ref } from 'vue';

// ── Types ─────────────────────────────────────────────────────────────────
// En TS, "type" définit la forme d'un objet.
// 'success' | 'error' est un "union type" : la valeur ne peut être que l'un
// des deux strings exacts. TS refusera 'warning' ou 'ok' à la compilation.

export type ToastType = 'success' | 'error';

export interface Toast {
    id: number;        // identifiant unique pour que Vue gère le DOM proprement (key)
    message: string;
    type: ToastType;
}

// ── État partagé (module-level) ───────────────────────────────────────────
// ref<Toast[]>([]) : un tableau réactif de Toast, initialement vide.
// Le générique <Toast[]> dit à TS : "ce ref contient un tableau de Toast".
// Sans lui, TS inférerait ref<never[]> et ne laisserait rien pousser dedans.
let nextId = 0;
const toasts = ref<Toast[]>([]);

// ── Composable ────────────────────────────────────────────────────────────
export function useToast() {

    function show(message: string, type: ToastType = 'success', duration = 4000): void {
        const id = nextId++;
        toasts.value.push({ id, message, type });

        // Retrait automatique après `duration` ms.
        setTimeout(() => {
            // Array.filter retourne un nouveau tableau sans l'élément supprimé.
            // Vue détecte le remplacement de .value et met à jour le DOM.
            toasts.value = toasts.value.filter(t => t.id !== id);
        }, duration);
    }

    // Raccourcis pratiques — pas obligatoires, mais évitent d'écrire le type partout.
    const success = (message: string) => show(message, 'success');
    const error   = (message: string) => show(message, 'error');

    function dismiss(id: number): void {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }

    // On expose `toasts` en lecture pour que Toast.vue puisse le lire,
    // mais on ne laisse pas l'extérieur pousser dedans directement —
    // tout passe par show() / dismiss().
    return { toasts, show, success, error, dismiss };
}
