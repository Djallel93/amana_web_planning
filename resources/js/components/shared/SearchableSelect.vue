<!-- resources/js/components/shared/SearchableSelect.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

// ── Props ─────────────────────────────────────────────────────────────────
// modelValue :
//   - mode simple  (multiple=false) → string ('' si rien sélectionné)
//   - mode multiple (multiple=true) → string[] ([] si rien sélectionné)
// Le composant garde un seul fichier pour les deux modes plutôt que d'en
// dupliquer un — la logique de fetch/cache/positionnement du dropdown est
// strictement identique, seule la sélection change.
const props = withDefaults(defineProps<{
    apiUrl:       string;
    modelValue:   string | string[];
    placeholder?: string;
    inputName?:   string;
    inputId?:     string;
    multiple?:    boolean;
}>(), {
    placeholder: 'Sélectionner un calendrier…',
    inputName:   '',
    inputId:     '',
    multiple:    false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string | string[]];
}>();

// ── État local ────────────────────────────────────────────────────────────
const isOpen     = ref(false);
const query      = ref('');
const calendars  = ref<string[]>([]);
const loading    = ref(false);
const fetchError = ref('');

// Ref sur le bouton déclencheur — nécessaire pour calculer la position
// du dropdown via getBoundingClientRect() une fois qu'il est téléporté dans body.
const triggerRef     = ref<HTMLButtonElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const wrapperRef     = ref<HTMLDivElement | null>(null);

// ── Position du dropdown (calculée depuis le bouton trigger) ──────────────
// Le dropdown est dans <Teleport to="body">, donc "position: absolute" ne
// peut plus se baser sur un ancêtre positionné — il faut des coordonnées
// viewport explicites (position: fixed + top/left calculés).
//
// Pourquoi fixed et pas absolute depuis body ?
// "absolute depuis body" serait affecté par le scroll de la page (le dropdown
// resterait à sa position initiale quand on scrolle). "fixed" est ancré au
// viewport — on recalcule top/left depuis getBoundingClientRect() qui retourne
// des coordonnées viewport, et le dropdown suit visuellement le bouton.
const dropdownStyle = ref({
    position: 'fixed' as const,
    top:      '0px',
    left:     '0px',
    width:    '0px',
    zIndex:   '9999',
});

function updateDropdownPosition(): void {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        position: 'fixed',
        top:      `${rect.bottom + 4}px`,
        left:     `${rect.left}px`,
        width:    `${rect.width}px`,
        zIndex:   '9999',
    };
}

// ── Cache module-level partagé entre toutes les instances ─────────────────
const _cache    = new Map<string, string[]>();
const _inflight = new Map<string, Promise<string[]>>();

// ── Helpers de lecture du modelValue selon le mode ─────────────────────────
const selectedValues = computed((): string[] => {
    if (!props.multiple) return [];
    return Array.isArray(props.modelValue) ? props.modelValue : [];
});

const selectedLabel = computed((): string => {
    if (props.multiple) return ''; // non utilisé en mode multiple (chips à la place)
    return typeof props.modelValue === 'string' ? props.modelValue : '';
});

function isSelected(cal: string): boolean {
    return props.multiple
        ? selectedValues.value.includes(cal)
        : cal === props.modelValue;
}

const filteredCalendars = computed(() => {
    const q = query.value.toLowerCase().trim();
    if (!q) return calendars.value;
    return calendars.value.filter(c => c.toLowerCase().includes(q));
});

// ── Fetch ─────────────────────────────────────────────────────────────────
async function fetchCalendars(): Promise<void> {
    if (_cache.has(props.apiUrl)) {
        calendars.value = _cache.get(props.apiUrl)!;
        return;
    }
    if (_inflight.has(props.apiUrl)) {
        calendars.value = await _inflight.get(props.apiUrl)!;
        return;
    }

    loading.value    = true;
    fetchError.value = '';

    const promise = fetch(props.apiUrl, {
        headers: {
            'Accept':            'application/json',
            'X-CSRF-TOKEN':      document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With':  'XMLHttpRequest',
        },
    })
        .then(r => r.json())
        .then((data: { calendars?: string[]; erreur?: string }) => {
            const result = data.calendars ?? [];
            _cache.set(props.apiUrl, result);
            if (data.erreur) fetchError.value = data.erreur;
            return result;
        })
        .catch(() => {
            fetchError.value = 'Impossible de contacter Make.com.';
            return [] as string[];
        })
        .finally(() => {
            _inflight.delete(props.apiUrl);
            loading.value = false;
        });

    _inflight.set(props.apiUrl, promise);
    calendars.value = await promise;
}

// ── Ouvrir / fermer ────────────────────────────────────────────────────────
async function open(): Promise<void> {
    if (isOpen.value) return;
    // Calculer la position AVANT d'ouvrir, pendant que le trigger est en place.
    updateDropdownPosition();
    isOpen.value = true;
    query.value  = '';
    if (!calendars.value.length) await fetchCalendars();
    await nextTick();
    searchInputRef.value?.focus();
}

function close(): void {
    isOpen.value = false;
    query.value  = '';
}

function toggle(): void {
    isOpen.value ? close() : open();
}

/**
 * Sélectionne (ou bascule, en mode multiple) un calendrier.
 * Mode simple  : émet la valeur et ferme le dropdown (comportement d'origine).
 * Mode multiple : bascule l'entrée dans le tableau, ne ferme PAS le dropdown
 *                 (l'utilisateur peut cocher plusieurs calendriers d'affilée).
 */
function select(value: string): void {
    if (!props.multiple) {
        emit('update:modelValue', value);
        close();
        return;
    }

    const current = selectedValues.value;
    const next = current.includes(value)
        ? current.filter(v => v !== value)
        : [...current, value];
    emit('update:modelValue', next);
}

function removeChip(value: string): void {
    emit('update:modelValue', selectedValues.value.filter(v => v !== value));
}

function clear(): void {
    emit('update:modelValue', props.multiple ? [] : '');
    if (!props.multiple) close();
}

// ── Fermeture par clic extérieur ───────────────────────────────────────────
// Le dropdown est dans <body> via Teleport — wrapperRef (le composant racine)
// et le dropdown lui-même sont deux sous-arbres DOM distincts. On doit
// vérifier les deux pour ne pas fermer quand on clique à l'intérieur du dropdown.
const dropdownRef = ref<HTMLDivElement | null>(null);

function onDocClick(e: MouseEvent): void {
    const target = e.target as Node;
    if (
        wrapperRef.value?.contains(target) ||
        dropdownRef.value?.contains(target)
    ) return;
    close();
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape') close();
}

// Recalcule la position si la page défile ou si la fenêtre est redimensionnée
// pendant que le dropdown est ouvert — pour qu'il reste aligné sur le trigger.
function onScrollOrResize(): void {
    if (isOpen.value) updateDropdownPosition();
}

onMounted(() => {
    document.addEventListener('click', onDocClick, true); // capture phase pour fiabilité
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);
    fetchCalendars(); // pré-chargement en arrière-plan
});

onUnmounted(() => {
    document.removeEventListener('click', onDocClick, true);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('scroll', onScrollOrResize, true);
    window.removeEventListener('resize', onScrollOrResize);
});
</script>

<template>
    <div ref="wrapperRef" class="relative w-full">

        <!--
            Inputs cachés pour soumission du form Blade.
            Mode simple   : un seul input, valeur = la chaîne sélectionnée.
            Mode multiple : un input par valeur sélectionnée, name="xxx[]"
                            pour que Laravel les reçoive comme un tableau —
                            même convention que des checkboxes natives.
        -->
        <template v-if="inputName">
            <input v-if="!multiple" type="hidden" :name="inputName" :id="inputId || undefined" :value="selectedLabel">
            <template v-else>
                <input v-for="val in selectedValues" :key="val" type="hidden" :name="`${inputName}[]`" :value="val">
            </template>
        </template>

        <!-- Bouton déclencheur -->
        <button
            ref="triggerRef"
            type="button"
            class="flex items-center justify-between gap-2 w-full px-3 py-2.5
                   border-[1.5px] rounded-lg bg-surface font-body text-[13.5px]
                   cursor-pointer text-left transition-colors min-h-[40px]"
            :class="isOpen
                ? 'border-accent shadow-glow'
                : 'border-ink-faint hover:border-accent'"
            @click="toggle"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
        >
            <!-- Mode multiple : chips des calendriers sélectionnés -->
            <span v-if="multiple" class="flex-1 flex flex-wrap gap-1.5 min-h-[20px]">
                <span v-if="!selectedValues.length" class="text-ink-muted">{{ placeholder }}</span>
                <span
                    v-for="val in selectedValues"
                    :key="val"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[12px] font-semibold bg-sky-50 text-accent"
                >
                    {{ val }}
                    <span
                        role="button"
                        aria-label="Retirer"
                        class="cursor-pointer opacity-60 hover:opacity-100 leading-none"
                        @click.stop="removeChip(val)"
                    >×</span>
                </span>
            </span>

            <!-- Mode simple : libellé unique (comportement d'origine) -->
            <span
                v-else
                class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap"
                :class="selectedLabel ? 'text-ink' : 'text-ink-muted'"
            >
                {{ selectedLabel || placeholder }}
            </span>

            <span
                class="flex-shrink-0 text-[10px] text-ink-muted transition-transform duration-150"
                :class="isOpen ? 'rotate-180' : ''"
            >▼</span>
        </button>

        <!--
            <Teleport to="body"> : le dropdown est rendu directement dans <body>,
            hors de tout ancêtre avec overflow:hidden. C'est la seule solution
            fiable quand le déclencheur est imbriqué dans une card overflow-hidden
            (comme la section "Synchronisation Google Calendar" dans evenements/form).

            La position est calculée dynamiquement via getBoundingClientRect()
            sur le bouton trigger (voir updateDropdownPosition()), et appliquée
            via :style sur le div du dropdown — coordonnées viewport (position: fixed).
        -->
        <Teleport to="body">
            <Transition name="dropdown">
                <div
                    v-if="isOpen"
                    ref="dropdownRef"
                    :style="dropdownStyle"
                    class="bg-surface border-[1.5px] border-ink-faint rounded-lg shadow-lg overflow-hidden"
                    role="listbox"
                    :aria-multiselectable="multiple"
                >
                    <!-- Champ recherche -->
                    <div class="px-2.5 pt-2.5 pb-1.5 border-b border-surface-3">
                        <input
                            ref="searchInputRef"
                            v-model="query"
                            type="text"
                            placeholder="Rechercher un calendrier…"
                            autocomplete="off"
                            class="w-full px-2.5 py-1.5 border-[1.5px] border-ink-faint rounded-lg
                                   text-[13px] font-body text-ink bg-surface-2 outline-none
                                   transition-colors focus:border-accent focus:shadow-glow"
                        >
                    </div>

                    <!-- Liste -->
                    <div class="max-h-[240px] overflow-y-auto py-1">

                        <div
                            v-if="loading"
                            class="flex items-center justify-center gap-2 py-4 text-[13px] text-ink-muted"
                        >
                            <span class="animate-spin">⏳</span> Chargement…
                        </div>

                        <div
                            v-else-if="fetchError && !calendars.length"
                            class="px-4 py-3 text-[12.5px] text-rose-700 bg-rose-50 border-t border-rose-200"
                        >
                            ⚠️ {{ fetchError }}<br>
                            <span class="text-[11.5px]">Vérifiez la configuration Make.com.</span>
                        </div>

                        <div
                            v-else-if="!filteredCalendars.length"
                            class="py-4 px-4 text-center text-[13px] text-ink-muted"
                        >
                            <template v-if="query">Aucun résultat pour « {{ query }} »</template>
                            <template v-else>Aucun calendrier disponible</template>
                        </div>

                        <div
                            v-for="cal in filteredCalendars"
                            :key="cal"
                            role="option"
                            :aria-selected="isSelected(cal)"
                            class="flex items-center gap-2 px-3.5 py-2.5 text-[13.5px] text-ink cursor-pointer transition-colors
                                   overflow-hidden text-ellipsis whitespace-nowrap"
                            :class="isSelected(cal)
                                ? 'bg-sky-50 text-accent font-semibold'
                                : 'hover:bg-surface-2'"
                            @click="select(cal)"
                        >
                            <span v-if="multiple" class="flex-shrink-0 w-4 h-4 rounded border-[1.5px] flex items-center justify-center text-[10px]"
                                  :class="isSelected(cal) ? 'bg-accent border-accent text-white' : 'border-ink-faint'">
                                <span v-if="isSelected(cal)">✓</span>
                            </span>
                            <span class="flex-1 overflow-hidden text-ellipsis whitespace-nowrap">{{ cal }}</span>
                        </div>
                    </div>

                    <!-- Effacer -->
                    <div class="px-3.5 py-2 border-t border-surface-3">
                        <button
                            type="button"
                            class="text-[12px] text-ink-muted hover:text-rose-600 bg-transparent
                                   border-0 cursor-pointer p-0 underline font-body transition-colors"
                            @click="clear"
                        >✕ Effacer la sélection</button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-6px) scale(0.98);
}
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
</style>
