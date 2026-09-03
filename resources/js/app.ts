// resources/js/app.ts
import { createApp } from "vue";

import {
    Toast,
    ConfirmDialog,
    OfflineBanner,
    MobileSidebar,
    registerThemeToggle,
    registerConfirmForms,
} from "@amana/shared-ui";
import SwapRequestModal from "@/components/mon-planning/SwapRequestModal.vue";
// 04/09/2026 : SearchableSelect promu vers @amana/shared-ui (roadmap mobile
// §4.3/step 7) — plus d'import local, mêmes props qu'avant, comportement
// identique (voir amana_shared_ui/src/components/SearchableSelect.vue).
import { SearchableSelect } from "@amana/shared-ui";
import HoraireSettings from "@/components/settings/HoraireSettings.vue";
import EventTaskBlocker from "@/components/evenements/EventTaskBlocker.vue";
import BulkEvenementImport from "@/components/evenements/BulkEvenementImport.vue";
import GeneratePreview from "@/components/planning-generate/GeneratePreview.vue";
import PlanningGrid from "@/components/planning/PlanningGrid.vue";
import EditAbsenceModal from "@/components/absences/EditAbsenceModal.vue";
import BilanView from "@/components/bilan/BilanView.vue";
import BilanStatistiques from "@/components/bilan/BilanStatistiques.vue";
import JournalAudit from "@/components/admin/JournalAudit.vue";
import ActiviteStatistiques from "@/components/admin/ActiviteStatistiques.vue";
import { registerUnsavedChangesGuard } from "@/lib/unsavedChanges";

registerThemeToggle();
registerUnsavedChangesGuard();
registerConfirmForms();

function mountIfPresent(
    selector: string,
    component: Parameters<typeof createApp>[0],
): void {
    const el = document.getElementById(selector);
    if (el) createApp(component).mount(el);
}

// ── Montages simples (un par page) ────────────────────────────────────────
mountIfPresent("vue-toast", Toast);
mountIfPresent("vue-confirm-dialog", ConfirmDialog);
mountIfPresent("vue-offline-banner", OfflineBanner);
mountIfPresent("vue-swap-modal", SwapRequestModal);
mountIfPresent("vue-horaire-settings", HoraireSettings);
mountIfPresent("vue-event-blocker", EventTaskBlocker);
mountIfPresent("vue-generate-preview", GeneratePreview);
mountIfPresent("vue-planning-grid", PlanningGrid);
mountIfPresent("vue-mobile-sidebar", MobileSidebar);
mountIfPresent("vue-edit-absence-modal", EditAbsenceModal);
mountIfPresent("vue-bilan", BilanView);
mountIfPresent("vue-bilan-statistiques", BilanStatistiques);
mountIfPresent("vue-journal-audit", JournalAudit);
mountIfPresent("vue-activite-statistiques", ActiviteStatistiques);

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
import { h } from "vue";

document
    .querySelectorAll<HTMLElement>("[data-searchable-select]")
    .forEach((el) => {
        const apiUrl = el.dataset.apiUrl ?? "";
        const inputName = el.dataset.inputName ?? "";
        const inputId = el.dataset.inputId ?? "";
        const placeholder = el.dataset.placeholder;
        const currentValue = el.dataset.currentValue ?? "";

        // ── Mode multiple (data-multiple="1") ──────────────────────────────────
        // data-current-value contient alors un JSON stringifié (ex: événements —
        // un événement peut être synchronisé sur plusieurs calendriers). En mode
        // simple (settings, un calendrier par tâche), data-current-value reste
        // une chaîne brute — comportement inchangé.
        const multiple = el.dataset.multiple === "1";

        let initialValue: string | string[] = currentValue;
        if (multiple) {
            try {
                const parsed = JSON.parse(currentValue || "[]");
                initialValue = Array.isArray(parsed) ? parsed : [];
            } catch {
                initialValue = [];
            }
        }

        const app = createApp({
            data() {
                return { value: initialValue };
            },
            render() {
                return h(SearchableSelect, {
                    modelValue: this.value,
                    "onUpdate:modelValue": (v: string | string[]) => {
                        this.value = v;
                    },
                    apiUrl,
                    inputName,
                    inputId,
                    multiple,
                    // Préserve le message d'erreur d'origine, spécifique à
                    // Google Calendar (04/09/2026) — le message par défaut
                    // du composant partagé est générique.
                    errorMessage:
                        "Impossible de contacter Google Calendar. Vérifiez la configuration Google Calendar.",
                    ...(placeholder ? { placeholder } : {}),
                });
            },
        });
        app.mount(el);
    });

// ── Montage BulkEvenementImport (saisie manuelle multi-lignes) ────────────
// evenements/import.blade.php — un seul point de montage par page. Les
// données (tâches, palette de couleurs, URL API calendriers) et l'état de
// réhydratation après erreur de validation (old('rows'), messages
// d'erreur) sont sérialisés en JSON côté Blade dans des data-attributes,
// même stratégie que le bloc SearchableSelect ci-dessus.
document
    .querySelectorAll<HTMLElement>("[data-bulk-evenement-import]")
    .forEach((el) => {
        const taches = JSON.parse(el.dataset.taches ?? "[]");
        const couleurs = JSON.parse(el.dataset.couleurs ?? "[]");
        const calendarsApiUrl = el.dataset.calendarsApiUrl ?? "";
        const oldRows = JSON.parse(el.dataset.oldRows ?? "[]");
        const errors = JSON.parse(el.dataset.errors ?? "{}");

        createApp({
            render: () =>
                h(BulkEvenementImport, {
                    taches,
                    couleurs,
                    calendarsApiUrl,
                    oldRows,
                    errors,
                }),
        }).mount(el);
    });
