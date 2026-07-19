// resources/js/lib/confirmForms.ts
//
// Remplace confirm() natif du navigateur par la boîte stylée ConfirmDialog.vue
// pour TOUS les formulaires <form> classiques rendus directement en Blade
// (pas de composant Vue par page nécessaire — <ConfirmDialog> est déjà monté
// une seule fois dans le layout, voir app.ts / #vue-confirm-dialog).
//
// Usage côté Blade — remplacer :
//   <form onsubmit="return confirm('Supprimer « X » ?')">
// par :
//   <form data-confirm="Supprimer « X » ?">
//   <form data-confirm="Supprimer « X » ?" data-confirm-danger>          (bouton rouge, icône ⚠️)
//   <form data-confirm="Supprimer « X » ?" data-confirm-label="Retirer"> (libellé du bouton de confirmation)
//
// confirm() natif est synchrone ; useConfirm().ask() est asynchrone (Promise).
// On intercepte donc submit, on empêche la soumission par défaut, on attend
// la réponse de l'utilisateur, puis on soumet le formulaire nous-mêmes si
// confirmé (même pattern que GeneratePreview.vue::onRollbackSubmitClick()).

import { useConfirm } from '@/composables/useConfirm';

const { ask } = useConfirm();

// Un formulaire déjà "confirmé" est soumis programmatiquement une seconde
// fois (form.submit()) — on doit alors laisser passer cet événement submit
// sans réintercepter, sous peine de boucle infinie.
const bypassing = new WeakSet<HTMLFormElement>();

async function onSubmit(e: SubmitEvent): Promise<void> {
    const form = e.target as HTMLFormElement;
    if (!(form instanceof HTMLFormElement)) return;

    const message = form.dataset.confirm;
    if (!message) return; // Formulaire non concerné — comportement inchangé.

    if (bypassing.has(form)) {
        bypassing.delete(form);
        return; // Deuxième soumission (programmatique, post-confirmation) — on laisse passer.
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    const confirmed = await ask({
        message,
        danger: 'confirmDanger' in form.dataset,
        confirmLabel: form.dataset.confirmLabel,
        title: form.dataset.confirmTitle,
    });

    if (confirmed) {
        bypassing.add(form);
        form.requestSubmit();
    }
}

// Exposée sur window pour les cas où le message de confirmation doit être
// construit dynamiquement au moment du submit (ex. admin/candidatures/index.blade.php
// ::confirmValidation(), le rôle sélectionné dans un <select> fait partie du
// message) — data-confirm seul ne suffit pas pour ce cas, son contenu étant
// figé au rendu Blade. Voir ce fichier pour l'usage (intercept + resoumission
// programmatique, même pattern que onSubmit() ci-dessus).
declare global {
    interface Window {
        amanaConfirm: (options: {
            message: string;
            danger?: boolean;
            confirmLabel?: string;
            title?: string;
        }) => Promise<boolean>;
    }
}

window.amanaConfirm = (options) => ask(options);

export function registerConfirmForms(): void {
    // capture: true — pour s'exécuter avant tout autre listener submit
    // éventuellement attaché au même formulaire.
    document.addEventListener('submit', (e) => {
        void onSubmit(e as SubmitEvent);
    }, true);
}
