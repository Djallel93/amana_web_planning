<!-- resources/js/components/evenements/EventTaskBlocker.vue -->
<!--
    Remplace le script inline de evenements/form.blade.php :
      - updateStatus()  : colorise les labels + compteur "X tâches bloquées"
      - toutCocher()    : sélectionne / désélectionne toutes les checkboxes
      - date_debut listener : contraint date_fin >= date_debut

    Même stratégie que HoraireSettings : pont DOM, le <form> reste Blade.

    ── Ce que ce composant fait vraiment ────────────────────────────────────
    1. Observe les changements sur les .tache-checkbox via un listener sur le form
    2. Recompute le compteur et les classes CSS sur chaque label
    3. Expose toutCocher() globalement pour les boutons "Tout bloquer / libérer"
    4. Gère la contrainte date_fin >= date_debut
-->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';

// ── État réactif ───────────────────────────────────────────────────────────
// On reflète l'état des checkboxes dans un Set réactif d'IDs cochés.
// Vue mettra à jour le compteur automatiquement dès que ce Set change.
const checkedIds = ref<Set<number>>(new Set());
const totalCount = ref(0);

// ── Compteur calculé ──────────────────────────────────────────────────────
// computed() : recalculé automatiquement quand checkedIds ou totalCount change.
const blockedCount = computed(() => checkedIds.value.size);

const blockedLabel = computed((): string => {
    const n = blockedCount.value;
    const t = totalCount.value;
    if (n === 0) return 'Événement informatif';
    if (n === t) return 'Toutes les tâches bloquées';
    return `${n} tâche${n > 1 ? 's' : ''} bloquée${n > 1 ? 's' : ''}`;
});

const blockedClass = computed((): string => {
    const n = blockedCount.value;
    const t = totalCount.value;
    const base = 'ml-auto text-[12px] font-semibold';
    if (n === 0)  return `${base} text-amber-600`;
    if (n === t)  return `${base} text-rose-600`;
    return `${base} text-amber-600`;
});

// ── Sync DOM → état Vue ────────────────────────────────────────────────────
function syncFromDom(): void {
    const newSet = new Set<number>();
    document.querySelectorAll<HTMLInputElement>('.tache-checkbox').forEach(cb => {
        if (cb.checked) newSet.add(parseInt(cb.value, 10));
    });
    checkedIds.value = newSet;
}

// ── Mise à jour des styles DOM ────────────────────────────────────────────
// On garde la mise à jour des classes CSS dans le DOM (pas dans le template Vue)
// parce que les labels .tache-block-item sont rendus par Blade @foreach.
function updateDomStyles(): void {
    document.querySelectorAll<HTMLInputElement>('.tache-checkbox').forEach(cb => {
        const label  = cb.closest<HTMLElement>('.tache-block-item');
        const status = label?.querySelector<HTMLElement>('.block-status');
        if (!label || !status) return;
        if (cb.checked) {
            label.classList.add('bg-rose-50');
            status.textContent = '🚫 Bloquée';
            status.className = status.className
                .replace(/\btext-emerald-600\b/g, '')
                .trim() + ' text-rose-600';
        } else {
            label.classList.remove('bg-rose-50');
            status.textContent = '✅ Libre';
            status.className = status.className
                .replace(/\btext-rose-600\b/g, '')
                .trim() + ' text-emerald-600';
        }
    });
}

// ── Mise à jour complète (état + DOM) ─────────────────────────────────────
function update(): void {
    syncFromDom();
    updateDomStyles();
    // Mise à jour du span compteur — il est dans le DOM Blade, pas dans le template Vue.
    // On le trouve et on met à jour ses classes/textContent depuis le composant.
    const el = document.getElementById('blockedCount');
    if (el) {
        el.textContent = blockedLabel.value;
        el.className   = blockedClass.value;
    }
}

// ── Actions publiques ──────────────────────────────────────────────────────
// Exposées sur window pour les boutons Blade onclick="toutCocher(true/false)".
declare global {
    interface Window {
        toutCocher: (state: boolean) => void;
    }
}

window.toutCocher = (state: boolean): void => {
    document.querySelectorAll<HTMLInputElement>('.tache-checkbox').forEach(cb => {
        cb.checked = state;
    });
    update();
};

// ── Contrainte date_fin >= date_debut ─────────────────────────────────────
function onDateDebutChange(e: Event): void {
    const debut = (e.target as HTMLInputElement).value;
    const finEl = document.getElementById('date_fin') as HTMLInputElement | null;
    if (!finEl) return;
    if (!finEl.value || finEl.value < debut) finEl.value = debut;
    finEl.min = debut;
}

// ── Cycle de vie ──────────────────────────────────────────────────────────
let checkboxContainer: HTMLElement | null = null;
let dateDebutEl: HTMLInputElement | null = null;

onMounted(() => {
    // Compter le total et initialiser l'état
    const checkboxes = document.querySelectorAll('.tache-checkbox');
    totalCount.value = checkboxes.length;

    // Délégation : écoute les changements sur le conteneur parent
    // plutôt que sur chaque checkbox individuellement.
    checkboxContainer = document.querySelector('.tache-block-item')?.parentElement ?? null;
    if (checkboxContainer) {
        checkboxContainer.addEventListener('change', update);
    }

    // Contrainte dates
    dateDebutEl = document.getElementById('date_debut') as HTMLInputElement | null;
    dateDebutEl?.addEventListener('change', onDateDebutChange);

    // État initial
    update();
});

onUnmounted(() => {
    checkboxContainer?.removeEventListener('change', update);
    dateDebutEl?.removeEventListener('change', onDateDebutChange);
});
</script>

<template>
    <!-- Composant invisible — même pattern que HoraireSettings -->
    <span aria-hidden="true"></span>
</template>
