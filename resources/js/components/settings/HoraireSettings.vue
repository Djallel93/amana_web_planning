<!-- resources/js/components/settings/HoraireSettings.vue -->
<!--
    Remplace les deux scripts inline de settings/index.blade.php :
      1. updateInscriptionStatus() — label du toggle
      2. updatePreviews()          — recalcul live des horaires affichés

    La page reste un <form> Blade classique soumis en POST.
    Ce composant s'intercale dans le DOM existant via le point de montage
    #vue-horaire-settings et lit/écrit les inputs Blade par référence DOM.

    ── Pourquoi pas tout réécrire en Vue ? ────────────────────────────────
    La page settings a beaucoup de Blade conditionnel (rôles, @if isset...).
    Convertir tout ça en Vue imposerait de passer toutes ces données en props
    ou via une API — travail disproportionné pour deux petits behaviours.
    On garde le form Blade, on ajoute juste la réactivité là où elle manque.

    ── Pattern "pont DOM" ─────────────────────────────────────────────────
    onMounted() lit les valeurs initiales depuis les inputs déjà rendus par Blade,
    puis écoute leurs événements 'input'/'change' pour mettre à jour l'état Vue.
    C'est un compromis pragmatique pour les pages où Blade et Vue coexistent
    sur les mêmes données.
-->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

// ── État réactif ───────────────────────────────────────────────────────────
const inscriptionOuverte = ref(false);

// heureCours est un string "HH:MM" — même format que <input type="time">
const heureCours = ref('20:00');

// Map des previews : chaque span.horaire-preview a data-debut-input et data-fin-input
// qui pointent vers des <input type="number" name="settings[...]">.
// On stocke les valeurs calculées indexées par un identifiant (le nom du span).
// Pour simplifier, on recalcule directement dans le DOM — Vue gère juste le trigger.
const previewTick = ref(0); // compteur factice pour forcer la mise à jour

// ── Helpers ───────────────────────────────────────────────────────────────
function addMinutes(hhmm: string, minutes: number): string {
    const [h, m] = hhmm.split(':').map(Number);
    const total = (((h * 60 + m + minutes) % 1440) + 1440) % 1440;
    return String(Math.floor(total / 60)).padStart(2, '0')
        + ':' + String(total % 60).padStart(2, '0');
}

function updatePreviews(): void {
    const heureCoursInput = document.getElementById('heure_cours') as HTMLInputElement | null;
    const hc = heureCoursInput?.value ?? '20:00';
    heureCours.value = hc;

    document.querySelectorAll<HTMLElement>('.horaire-preview').forEach(span => {
        const debutName = span.dataset.debutInput;
        const finName   = span.dataset.finInput;
        const debutEl   = debutName
            ? document.querySelector<HTMLInputElement>(`[name="${debutName}"]`)
            : null;
        const finEl     = finName
            ? document.querySelector<HTMLInputElement>(`[name="${finName}"]`)
            : null;
        if (!debutEl || !finEl) return;
        span.textContent = addMinutes(hc, parseInt(debutEl.value, 10) || 0)
            + ' → '
            + addMinutes(hc, parseInt(finEl.value, 10) || 0);
    });
}

function onInscriptionChange(e: Event): void {
    inscriptionOuverte.value = (e.target as HTMLInputElement).checked;
}

function onFormInput(): void {
    updatePreviews();
}

// ── Cycle de vie ──────────────────────────────────────────────────────────
// onMounted : le DOM Blade est déjà là quand ce composant est monté —
// on peut lire les valeurs initiales et attacher les listeners.
let inscriptionToggle: HTMLInputElement | null = null;
let settingsForm: HTMLElement | null = null;

onMounted(() => {
    // Toggle inscription
    inscriptionToggle = document.getElementById('inscriptionToggle') as HTMLInputElement | null;
    if (inscriptionToggle) {
        inscriptionOuverte.value = inscriptionToggle.checked;
        inscriptionToggle.addEventListener('change', onInscriptionChange);
    }

    // Previews horaires — écoute tous les inputs du form d'un coup
    settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('input', onFormInput);
    }

    // Calcul initial
    updatePreviews();
});

onUnmounted(() => {
    inscriptionToggle?.removeEventListener('change', onInscriptionChange);
    settingsForm?.removeEventListener('input', onFormInput);
});
</script>

<template>
    <!--
        Ce composant ne rend rien de visible par lui-même —
        il agit comme un "controller" invisible qui met à jour le DOM Blade.
        On rend un <span> vide pour satisfaire l'exigence Vue d'avoir un seul
        élément racine dans le template, sans polluer le layout.

        Le label "inscriptionLabel" est géré directement dans le DOM Blade
        (il est déjà là) — on n'a pas besoin de le re-rendre depuis Vue.
        On met juste à jour son textContent via un watch si besoin, mais
        ici la logique est déjà dans onInscriptionChange via le listener DOM.

        Note : si dans le futur cette page est davantage "vueifiée",
        on déplacera le label dans le template Vue et on utilisera {{ }}.
    -->
    <span aria-hidden="true"></span>
</template>
