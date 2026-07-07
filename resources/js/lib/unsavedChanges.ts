// resources/js/lib/unsavedChanges.ts
//
// Avertit l'utilisateur avant de quitter la page (fermeture d'onglet,
// rechargement, navigation) s'il a modifié un formulaire sans le soumettre.
//
// Fonctionne automatiquement pour TOUS les formulaires <form> classiques de
// l'app (POST/PUT/PATCH) sans modification page par page : on écoute les
// événements input/change au niveau document et on retient si un champ a
// été modifié depuis le dernier chargement/soumission.
//
// Formulaires exclus par défaut :
//   - method="GET"                    (filtres de recherche, etc. — rien à "perdre")
//   - data-no-dirty-check              (opt-out explicite, ex. formulaire de connexion)
//
// Pour les modals Vue (EditAbsenceModal, SwapRequestModal…), voir plutôt la
// prop confirmClose de Modal.vue — ce module ne couvre que les formulaires
// Blade traditionnels rendus directement dans le HTML de la page.

function isTrackableForm(form: HTMLFormElement): boolean {
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'get') return false;
    if (form.hasAttribute('data-no-dirty-check')) return false;
    return true;
}

let dirty = false;

function markDirty(e: Event): void {
    const target = e.target as HTMLElement | null;
    const form = target?.closest('form');
    if (form instanceof HTMLFormElement && isTrackableForm(form)) {
        dirty = true;
    }
}

function markCleanOnSubmit(e: Event): void {
    const form = e.target as HTMLFormElement;
    if (isTrackableForm(form)) {
        // Le formulaire est en train d'être soumis volontairement — plus
        // besoin d'avertir pour CE changement de page.
        dirty = false;
    }
}

export function registerUnsavedChangesGuard(): void {
    document.addEventListener('input', markDirty, true);
    document.addEventListener('change', markDirty, true);
    document.addEventListener('submit', markCleanOnSubmit, true);

    window.addEventListener('beforeunload', (e: BeforeUnloadEvent) => {
        if (!dirty) return;
        // Le navigateur affiche son propre message générique — aucun texte
        // personnalisé n'est plus supporté par les navigateurs modernes,
        // mais preventDefault()/returnValue déclenche bien la boîte native.
        e.preventDefault();
        e.returnValue = '';
    });
}
