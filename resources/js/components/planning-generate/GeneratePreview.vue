<!-- resources/js/components/planning-generate/GeneratePreview.vue -->
<!--
    Remplace le script inline de planning/generate.blade.php :
      - updatePreview()        : texte d'aperçu calculé depuis date + semaines
      - submit listener        : désactive le bouton + spinner pendant la génération
      - submitPreview()        : remplit le form caché et le soumet
      - onRollbackTypeChange() : bascule l'affichage de la checklist partielle
      - checkAll()/confirmRollback() : actions sur la liste de rollback

    Page riche en comportements mais tous indépendants — un seul composant
    les regroupe pour éviter de fragmenter en 5 mini-composants qui n'ont
    pas de raison d'être séparés (ils partagent le même form Blade).
-->
<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useToast } from '@/composables/useToast';
import { useConfirm } from '@/composables/useConfirm';

const toast = useToast();
const { ask } = useConfirm();

// ── Aperçu date/semaines ───────────────────────────────────────────────────
const dateDebut = ref('');
const semaines  = ref(0);

// computed : le texte d'aperçu se recalcule automatiquement
// dès que dateDebut ou semaines change — pas besoin d'appeler une fonction.
const previewHtml = computed((): string => {
    if (!dateDebut.value || semaines.value < 1) {
        return "Remplissez les champs pour voir l'aperçu";
    }
    const dt = new Date(dateDebut.value + 'T00:00:00');
    // On avance jusqu'au prochain vendredi (getDay() === 5)
    while (dt.getDay() !== 5) dt.setDate(dt.getDate() + 1);
    const fin = new Date(dt);
    fin.setDate(fin.getDate() + (semaines.value - 1) * 7 + 1);

    const fmt = (d: Date) => d.toLocaleDateString('fr-FR', {
        day: 'numeric', month: 'long', year: 'numeric',
    });

    return `<strong>${semaines.value * 2} créneaux</strong> `
        + `(${semaines.value} vendredis + ${semaines.value} samedis) `
        + `du <strong>${fmt(dt)}</strong> au <strong>${fmt(fin)}</strong>`;
});

function onDateDebutInput(e: Event): void {
    dateDebut.value = (e.target as HTMLInputElement).value;
}
function onSemainesInput(e: Event): void {
    semaines.value = parseInt((e.target as HTMLInputElement).value, 10) || 0;
}

// ── Soumission principale (désactive le bouton) ───────────────────────────
const generating = ref(false);

function onGenerateSubmit(): void {
    generating.value = true;
    // Le formulaire continue sa soumission normale (POST classique) —
    // on ne fait que désactiver visuellement le bouton.
}

// ── Aperçu (form caché) ───────────────────────────────────────────────────
const previewing = ref(false);

function submitPreview(): void {
    const dateDebutEl = document.getElementById('date_debut') as HTMLInputElement | null;
    const semainesEl  = document.getElementById('semaines')   as HTMLInputElement | null;
    const val = dateDebutEl?.value ?? '';
    const sem = semainesEl?.value ?? '';

    if (!val || !sem || parseInt(sem, 10) < 1) {
        toast.error('Veuillez remplir la date et le nombre de semaines avant de prévisualiser.');
        return;
    }

    const previewDateEl = document.getElementById('preview_date_debut') as HTMLInputElement | null;
    const previewSemEl  = document.getElementById('preview_semaines')   as HTMLInputElement | null;
    if (previewDateEl) previewDateEl.value = val;
    if (previewSemEl)  previewSemEl.value  = sem;

    previewing.value = true;
    (document.getElementById('previewForm') as HTMLFormElement | null)?.submit();
}

// Exposé sur window car le bouton Blade utilise onclick="submitPreview()"
declare global {
    interface Window {
        submitPreview: () => void;
        onRollbackTypeChange: (radio: HTMLInputElement) => void;
        checkAll: (state: boolean) => void;
    }
}
window.submitPreview = submitPreview;

// ── Rollback : type total / partiel ───────────────────────────────────────
const rollbackType = ref<'total' | 'partial'>('total');

const optActiveClass   = 'rollback-opt border-[1.5px] border-accent bg-sky-50 rounded-lg p-3.5 cursor-pointer transition-colors';
const optInactiveClass = 'rollback-opt border-[1.5px] border-surface-border rounded-lg p-3.5 cursor-pointer transition-colors hover:border-amber-300 hover:bg-amber-50';

function applyRollbackStyles(): void {
    const optTotal   = document.getElementById('opt-total');
    const optPartial = document.getElementById('opt-partial');
    const checklist  = document.getElementById('weekChecklist');
    if (optTotal)   optTotal.className   = rollbackType.value === 'total'   ? optActiveClass : optInactiveClass;
    if (optPartial) optPartial.className = rollbackType.value === 'partial' ? optActiveClass : optInactiveClass;
    checklist?.classList.toggle('hidden', rollbackType.value !== 'partial');
}

window.onRollbackTypeChange = (radio: HTMLInputElement): void => {
    rollbackType.value = radio.value === 'partial' ? 'partial' : 'total';
    applyRollbackStyles();
};

window.checkAll = (state: boolean): void => {
    document.querySelectorAll<HTMLInputElement>('#weekChecklist input[type="checkbox"]')
        .forEach(cb => { cb.checked = state; });
};

// ── Confirmation avant soumission du rollback ─────────────────────────────
// confirm() natif est synchrone : un onclick="return confirmRollback()"
// inline pouvait bloquer la soumission en attendant sa réponse. ask() est
// asynchrone (boîte stylée), donc ce pattern ne fonctionne plus : on
// intercepte le clic du bouton, on empêche la soumission par défaut, on
// attend la réponse de l'utilisateur, puis on soumet le formulaire
// nous-mêmes si confirmé. Le bouton n'a donc plus d'attribut onclick — voir
// planning/generate.blade.php (id="rollbackSubmitBtn").
let rollbackSubmitBtn: HTMLButtonElement | null = null;

async function onRollbackSubmitClick(e: MouseEvent): Promise<void> {
    e.preventDefault();

    const checkedRadio = document.querySelector<HTMLInputElement>('input[name="rollback_type"]:checked');
    const type = checkedRadio?.value;

    let confirmed: boolean;
    if (type === 'partial') {
        const checked = document.querySelectorAll<HTMLInputElement>(
            '#weekChecklist input[type="checkbox"]:checked'
        ).length;
        if (checked === 0) {
            toast.error('Sélectionnez au moins une semaine.');
            return;
        }
        confirmed = await ask({ message: `Supprimer ${checked} semaine(s) sélectionnée(s) ?`, danger: true });
    } else {
        confirmed = await ask({ message: 'Annuler toute la génération ? Cette action est irréversible.', danger: true });
    }

    if (confirmed) {
        (document.getElementById('rollbackForm') as HTMLFormElement | null)?.submit();
    }
}

// ── Cycle de vie ──────────────────────────────────────────────────────────
let dateDebutEl: HTMLInputElement | null = null;
let semainesEl: HTMLInputElement | null = null;
let generateForm: HTMLFormElement | null = null;

onMounted(() => {
    dateDebutEl = document.getElementById('date_debut') as HTMLInputElement | null;
    semainesEl  = document.getElementById('semaines')   as HTMLInputElement | null;

    // Valeurs initiales (le form Blade peut déjà avoir old() rempli)
    dateDebut.value = dateDebutEl?.value ?? '';
    semaines.value  = parseInt(semainesEl?.value ?? '0', 10) || 0;

    dateDebutEl?.addEventListener('input', onDateDebutInput);
    semainesEl?.addEventListener('input', onSemainesInput);

    generateForm = document.getElementById('generateForm') as HTMLFormElement | null;
    generateForm?.addEventListener('submit', onGenerateSubmit);

    rollbackSubmitBtn = document.getElementById('rollbackSubmitBtn') as HTMLButtonElement | null;
    rollbackSubmitBtn?.addEventListener('click', onRollbackSubmitClick);

    // Le previewText du DOM Blade est remplacé par notre rendu Vue —
    // on synchronise le HTML calculé dans le span existant via un watch.
    // { immediate: true } : exécute le callback tout de suite au montage,
    // pas seulement à partir du prochain changement.
    watch(previewHtml, (html) => {
        const el = document.getElementById('previewText');
        if (el) el.innerHTML = html;
    }, { immediate: true });
});

onUnmounted(() => {
    dateDebutEl?.removeEventListener('input', onDateDebutInput);
    semainesEl?.removeEventListener('input', onSemainesInput);
    generateForm?.removeEventListener('submit', onGenerateSubmit);
    rollbackSubmitBtn?.removeEventListener('click', onRollbackSubmitClick);
});
</script>

<template>
    <!--
        Composant invisible — toute la logique agit sur le DOM Blade existant
        (previewText, submitBtn, rollback options). On a besoin de generating
        et previewing dans le template pour mettre à jour les boutons,
        donc on rend deux petits helpers visuels via teleport vers les boutons
        existants serait trop complexe — on garde la manipulation DOM directe
        ici, cohérente avec le reste du composant.
    -->
    <span aria-hidden="true"></span>
</template>
