// resources/js/app.ts
import { createApp } from 'vue';

import Toast             from '@/components/shared/Toast.vue';
import SwapRequestModal  from '@/components/mon-planning/SwapRequestModal.vue';
import SearchableSelect  from '@/components/shared/SearchableSelect.vue';
import HoraireSettings   from '@/components/settings/HoraireSettings.vue';
import EventTaskBlocker  from '@/components/evenements/EventTaskBlocker.vue';
import GeneratePreview   from '@/components/planning-generate/GeneratePreview.vue';
import PlanningGrid      from '@/components/planning/PlanningGrid.vue';
import MobileSidebar     from '@/components/shared/MobileSidebar.vue';

function mountIfPresent(
    selector: string,
    component: Parameters<typeof createApp>[0]
): void {
    const el = document.getElementById(selector);
    if (el) createApp(component).mount(el);
}

// ── Montages simples (un par page) ────────────────────────────────────────
mountIfPresent('vue-toast',           Toast);
mountIfPresent('vue-swap-modal',      SwapRequestModal);
mountIfPresent('vue-horaire-settings', HoraireSettings);
mountIfPresent('vue-event-blocker',   EventTaskBlocker);
mountIfPresent('vue-generate-preview', GeneratePreview);
mountIfPresent('vue-planning-grid',   PlanningGrid);
mountIfPresent('vue-mobile-sidebar',  MobileSidebar);

// ── Montages multiples (SearchableSelect : plusieurs instances par page) ──
// settings/index.blade.php a 9 instances (une par calendrier de tâche).
// Chaque instance porte un data-input-name unique sur son point de montage,
// et un data-current-value pré-rempli par Blade (valeur déjà enregistrée).
// On monte une instance Vue distincte par élément trouvé.
//
// ── Pourquoi h() et pas un template string ? ──────────────────────────────
// Le build Vite de cette app utilise le runtime Vue "runtime-only" (sans le
// compilateur de templates embarqué — c'est le défaut de @vitejs/plugin-vue,
// pour garder le bundle léger). Un composant défini avec `template: '...'`
// nécessite ce compilateur à l'exécution et échoue silencieusement sans lui
// (c'était le bug : le point de montage restait vide).
// h() (hyperscript) construit l'arbre de rendu directement en JS, sans
// jamais avoir besoin de compiler de template — il fonctionne avec le
// runtime seul, donc avec notre configuration actuelle.
import { h } from 'vue';

document.querySelectorAll<HTMLElement>('[data-searchable-select]').forEach(el => {
    const apiUrl      = el.dataset.apiUrl ?? '';
    const inputName   = el.dataset.inputName ?? '';
    const inputId     = el.dataset.inputId ?? '';
    const placeholder = el.dataset.placeholder;
    const currentValue = el.dataset.currentValue ?? '';

    const app = createApp({
        data() {
            return { value: currentValue };
        },
        render() {
            return h(SearchableSelect, {
                modelValue: this.value,
                'onUpdate:modelValue': (v: string) => { this.value = v; },
                apiUrl,
                inputName,
                inputId,
                ...(placeholder ? { placeholder } : {}),
            });
        },
    });
    app.mount(el);
});
