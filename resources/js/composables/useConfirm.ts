// resources/js/composables/useConfirm.ts
//
// Composable pour des boîtes de confirmation stylées, en remplacement de
// confirm() natif du navigateur. Pattern "état partagé au niveau module" —
// identique à useToast.ts — pour qu'un seul <ConfirmDialog> monté une fois
// dans le layout (voir app.ts, div #vue-confirm-dialog) puisse être piloté
// depuis n'importe quel composant via useConfirm().ask(...).
//
// Contrairement à confirm() natif (synchrone, bloquant), ask() est
// asynchrone : tout code appelant DOIT faire `await` dessus. Attention en
// particulier si un bouton est relié à un <form> HTML classique via un
// attribut onclick="return ..." — cet attribut attend un booléen synchrone
// et ne fonctionnera pas avec ask(). Dans ce cas, préférer
// e.preventDefault() + soumission programmatique du formulaire une fois la
// promesse résolue (voir GeneratePreview.vue, confirmRollback()).

import { ref } from 'vue';

export interface ConfirmOptions {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Style "danger" (bouton rouge, icône ⚠️) pour les suppressions/actions irréversibles. */
    danger?: boolean;
}

interface ConfirmState {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    danger: boolean;
}

const state = ref<ConfirmState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: 'Confirmer',
    cancelLabel: 'Annuler',
    danger: false,
});

// Une seule confirmation peut être ouverte à la fois — resolver courant.
let resolver: ((value: boolean) => void) | null = null;

export function useConfirm() {
    /**
     * Ouvre la boîte de confirmation et retourne une promesse résolue à
     * true (confirmé) ou false (annulé / fermé via Escape ou backdrop).
     */
    function ask(options: ConfirmOptions): Promise<boolean> {
        // Si une confirmation précédente était encore en attente (ne devrait
        // pas arriver en usage normal), on la résout à false plutôt que de
        // la laisser bloquée indéfiniment.
        resolver?.(false);

        state.value = {
            open: true,
            title: options.title ?? (options.danger ? 'Confirmer la suppression' : 'Confirmation'),
            message: options.message,
            confirmLabel: options.confirmLabel ?? (options.danger ? 'Supprimer' : 'Confirmer'),
            cancelLabel: options.cancelLabel ?? 'Annuler',
            danger: options.danger ?? false,
        };

        return new Promise<boolean>((resolve) => {
            resolver = resolve;
        });
    }

    /** Appelé par ConfirmDialog.vue quand l'utilisateur répond. */
    function respond(value: boolean): void {
        state.value.open = false;
        resolver?.(value);
        resolver = null;
    }

    return { state, ask, respond };
}
