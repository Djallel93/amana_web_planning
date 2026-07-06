<!-- resources/js/components/admin/JournalAudit.vue -->
<!--
    Vue racine de la page Journal d'audit (admin uniquement).

    Tableau paginé des entrées audit_logs, filtrable par module / action /
    utilisateur / plage de dates. Chaque ligne peut être dépliée pour voir le
    détail avant/après (before/after JSON) de l'action.

    Volontairement en lecture seule : aucune action de restauration n'est
    proposée depuis cette vue (voir commentaire dans AuditLogController).
-->
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';

// ── Types ─────────────────────────────────────────────────────────────────
interface Entree {
    id: number;
    date: string;
    utilisateur: string;
    action: string;
    module: string;
    entityId: number | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ipAddress: string | null;
    userAgent: string | null;
}

interface Meta {
    current_page: number;
    last_page: number;
    total: number;
}

interface Personne {
    id: number;
    nom: string;
}

declare global {
    interface Window {
        JournalAuditConfig: {
            csrf: string;
            routes: { data: string };
            modules: string[];
            actions: string[];
            personnes: Personne[];
        };
    }
}

const config = window.JournalAuditConfig;

// ── Libellés français pour affichage ────────────────────────────────────
const LIBELLES_ACTION: Record<string, string> = {
    create: 'Création', update: 'Modification', delete: 'Suppression',
    generate: 'Génération', login: 'Connexion', logout: 'Déconnexion',
    webhook: 'Webhook',
};

const COULEURS_ACTION: Record<string, string> = {
    create: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    update: 'bg-sky-50 text-sky-700 border-sky-200',
    delete: 'bg-rose-50 text-rose-700 border-rose-200',
    generate: 'bg-violet-50 text-violet-700 border-violet-200',
    login: 'bg-slate-50 text-slate-600 border-slate-200',
    logout: 'bg-slate-50 text-slate-600 border-slate-200',
    webhook: 'bg-amber-50 text-amber-700 border-amber-200',
};

function libelleAction(action: string): string {
    return LIBELLES_ACTION[action] ?? action;
}

function couleurAction(action: string): string {
    return COULEURS_ACTION[action] ?? 'bg-slate-50 text-slate-600 border-slate-200';
}

// ── Filtres ───────────────────────────────────────────────────────────────
const filtreModule = ref('');
const filtreAction = ref('');
const filtreUtilisateur = ref('');
const filtreDe = ref('');
const filtreA = ref('');
const page = ref(1);

const aDesFiltresActifs = computed(() =>
    !!(filtreModule.value || filtreAction.value || filtreUtilisateur.value || filtreDe.value || filtreA.value)
);

function reinitialiserFiltres(): void {
    filtreModule.value = '';
    filtreAction.value = '';
    filtreUtilisateur.value = '';
    filtreDe.value = '';
    filtreA.value = '';
    page.value = 1;
}

// ── État ──────────────────────────────────────────────────────────────────
const entrees = ref<Entree[]>([]);
const meta = ref<Meta | null>(null);
const lignesDepliees = ref<Set<number>>(new Set());

type LoadState = 'idle' | 'loading' | 'loaded' | 'error';
const loadState = ref<LoadState>('idle');

function getCsrf(): string {
    return config?.csrf
        ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
        ?? '';
}

// ── Chargement ────────────────────────────────────────────────────────────
async function load(): Promise<void> {
    loadState.value = 'loading';
    try {
        const params = new URLSearchParams();
        if (filtreModule.value) params.set('module', filtreModule.value);
        if (filtreAction.value) params.set('action', filtreAction.value);
        if (filtreUtilisateur.value) params.set('user_id', filtreUtilisateur.value);
        if (filtreDe.value) params.set('from', filtreDe.value);
        if (filtreA.value) params.set('to', filtreA.value);
        params.set('page', String(page.value));

        const res = await fetch(`${config.routes.data}?${params.toString()}`, {
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json() as { data: Entree[]; meta: Meta };
        entrees.value = data.data;
        meta.value = data.meta;
        lignesDepliees.value = new Set();
        loadState.value = 'loaded';
    } catch {
        loadState.value = 'error';
    }
}

// Tout changement de filtre revient à la page 1 puis recharge.
watch([filtreModule, filtreAction, filtreUtilisateur, filtreDe, filtreA], () => {
    page.value = 1;
    load();
});
watch(page, () => load());
onMounted(() => load());

function basculerLigne(id: number): void {
    if (lignesDepliees.value.has(id)) {
        lignesDepliees.value.delete(id);
    } else {
        lignesDepliees.value.add(id);
    }
    // Forcer la réactivité (Set n'est pas profondément réactif sur .has() seul)
    lignesDepliees.value = new Set(lignesDepliees.value);
}

function allerPage(n: number): void {
    if (!meta.value || n < 1 || n > meta.value.last_page) return;
    page.value = n;
}
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- Filtres -->
        <div class="bg-surface rounded-xl border border-surface-border shadow-sm px-5 py-4 flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label for="f_module" class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px]">Module</label>
                <select id="f_module" v-model="filtreModule"
                    class="px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    <option value="">Tous</option>
                    <option v-for="m in config.modules" :key="m" :value="m">{{ m }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="f_action" class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px]">Action</label>
                <select id="f_action" v-model="filtreAction"
                    class="px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    <option value="">Toutes</option>
                    <option v-for="a in config.actions" :key="a" :value="a">{{ libelleAction(a) }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="f_user" class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px]">Utilisateur</label>
                <select id="f_user" v-model="filtreUtilisateur"
                    class="px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                    <option value="">Tous</option>
                    <option v-for="p in config.personnes" :key="p.id" :value="p.id">{{ p.nom }}</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label for="f_de" class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px]">Du</label>
                <input id="f_de" type="date" v-model="filtreDe" :max="filtreA || undefined"
                    class="px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
            </div>

            <div class="flex flex-col gap-1">
                <label for="f_a" class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px]">Au</label>
                <input id="f_a" type="date" v-model="filtreA" :min="filtreDe || undefined"
                    class="px-3 py-2 border-[1.5px] border-ink-faint rounded-lg text-[13px] font-body text-ink bg-surface-2 outline-none transition
                           focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
            </div>

            <button v-if="aDesFiltresActifs" type="button" @click="reinitialiserFiltres"
                class="px-3 py-2 text-[12.5px] font-semibold text-ink-muted hover:text-ink transition-colors">
                ✕ Réinitialiser
            </button>

            <span v-if="meta" class="text-[12.5px] text-ink-muted ml-auto">
                {{ meta.total }} entrée(s)
            </span>
        </div>

        <!-- Chargement / erreur -->
        <div v-if="loadState === 'loading'" class="text-center py-10 text-[13.5px] text-ink-muted">
            ⏳ Chargement du journal…
        </div>
        <div v-else-if="loadState === 'error'" class="text-center py-8 text-rose-600 text-[13px]">
            ❌ Erreur lors du chargement du journal.
        </div>

        <template v-else-if="loadState === 'loaded'">
            <div v-if="!entrees.length"
                class="text-center py-10 px-4 text-[13.5px] text-ink-muted bg-surface-2 rounded-lg border border-surface-border">
                😕 Aucune entrée ne correspond à ces filtres.
            </div>

            <div v-else class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-surface-border bg-surface-2 text-left">
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">Date</th>
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">Utilisateur</th>
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">Action</th>
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">Module</th>
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">Entité</th>
                            <th class="px-4 py-2.5 font-bold text-[11px] uppercase tracking-[0.4px] text-ink-muted">IP</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="entree in entrees" :key="entree.id">
                            <tr class="border-b border-surface-border last:border-0 hover:bg-surface-2/60 transition-colors">
                                <td class="px-4 py-2.5 text-ink-muted whitespace-nowrap">{{ entree.date }}</td>
                                <td class="px-4 py-2.5 text-ink font-medium">{{ entree.utilisateur }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold border"
                                        :class="couleurAction(entree.action)">
                                        {{ libelleAction(entree.action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ entree.module }}</td>
                                <td class="px-4 py-2.5 text-ink-muted">{{ entree.entityId ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-ink-muted font-mono text-[11.5px]">{{ entree.ipAddress ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <button
                                        v-if="entree.before || entree.after"
                                        type="button" @click="basculerLigne(entree.id)"
                                        class="text-[12px] font-semibold text-accent hover:underline">
                                        {{ lignesDepliees.has(entree.id) ? 'Masquer' : 'Détail' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="lignesDepliees.has(entree.id)" class="border-b border-surface-border last:border-0 bg-surface-2/40">
                                <td colspan="7" class="px-4 py-3">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px] mb-1">Avant</p>
                                            <pre class="text-[11.5px] font-mono bg-surface border border-surface-border rounded-lg p-3 overflow-x-auto max-h-64">{{ entree.before ? JSON.stringify(entree.before, null, 2) : '—' }}</pre>
                                        </div>
                                        <div>
                                            <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.4px] mb-1">Après</p>
                                            <pre class="text-[11.5px] font-mono bg-surface border border-surface-border rounded-lg p-3 overflow-x-auto max-h-64">{{ entree.after ? JSON.stringify(entree.after, null, 2) : '—' }}</pre>
                                        </div>
                                    </div>
                                    <p v-if="entree.userAgent" class="text-[11px] text-ink-muted mt-2 truncate">
                                        Agent : {{ entree.userAgent }}
                                    </p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2">
                <button type="button" @click="allerPage(page - 1)" :disabled="page <= 1"
                    class="px-3 py-1.5 text-[12.5px] font-semibold rounded-lg border border-surface-border
                           text-ink-muted hover:text-ink hover:bg-surface-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    ← Précédent
                </button>
                <span class="text-[12.5px] text-ink-muted px-2">Page {{ meta.current_page }} / {{ meta.last_page }}</span>
                <button type="button" @click="allerPage(page + 1)" :disabled="page >= meta.last_page"
                    class="px-3 py-1.5 text-[12.5px] font-semibold rounded-lg border border-surface-border
                           text-ink-muted hover:text-ink hover:bg-surface-2 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    Suivant →
                </button>
            </div>
        </template>
    </div>
</template>
