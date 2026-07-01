<!-- resources/js/components/bilan/BilanStatistiques.vue -->
<!--
    Vue racine de la page Statistiques Bilan.

    Un seul graphique à deux courbes (axes Y séparés, unités différentes) :
      - Présence totale du jour (Présents + En ligne)     → axe gauche
      - Montant total collecté du jour (Carte + Espèces)  → axe droit

    Au survol d'un point, le tooltip Chart.js (mode "index", partagé entre
    les deux courbes) affiche le détail de chaque courbe : répartition
    Présents/En ligne + responsable mektaba pour la présence, répartition
    Carte/Espèces + responsable amana food pour le montant. Ces infos ne
    sont pas dans le dataset Chart.js lui-même (juste les totaux) — le
    callback de tooltip va les rechercher dans `serie` par dataIndex.
-->
<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    Filler,
    type ChartConfiguration,
    type TooltipItem,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

// ── Types ─────────────────────────────────────────────────────────────────
interface SeriePoint {
    date:                 string; // ISO "2026-06-12"
    totalPresence:        number;
    totalMontant:         number;
    nbPresents:           number;
    nbEnLigne:            number;
    montantCarte:         number;
    montantEspece:        number;
    responsableAmanaFood: string | null;
    responsableMektaba:   string | null;
}

interface DateValeur {
    date:   string;
    valeur: number;
}

interface Cartes {
    totalMontant:      number;
    moyennePresence:   number;
    meilleureDate:     DateValeur | null;
    meilleureCollecte: DateValeur | null;
    tauxRemplissage:   number | null;
    nbBilans:          number;
    nbCreneaux:        number;
}

declare global {
    interface Window {
        BilanStatistiquesConfig: {
            csrf:   string;
            routes: { data: string };
        };
    }
}

// ── État ──────────────────────────────────────────────────────────────────
function isoDaysAgo(n: number): string {
    const d = new Date();
    d.setDate(d.getDate() - n);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const dateFrom = ref(isoDaysAgo(29)); // 30 derniers jours (bornes incluses)
const dateTo   = ref(isoDaysAgo(0));

const serie  = ref<SeriePoint[]>([]);
const cartes = ref<Cartes | null>(null);

type LoadState = 'idle' | 'loading' | 'loaded' | 'error';
const loadState = ref<LoadState>('idle');

// ── CSRF ──────────────────────────────────────────────────────────────────
function getCsrf(): string {
    return window.BilanStatistiquesConfig?.csrf
        ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
        ?? '';
}

// ── Formatage ─────────────────────────────────────────────────────────────
function fmtEuro(v: number): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v);
}

function fmtDateLabel(iso: string): string {
    return new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });
}

function fmtDateCourt(iso: string): string {
    return new Date(iso + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
}

// ── Chargement ────────────────────────────────────────────────────────────
async function load(): Promise<void> {
    loadState.value = 'loading';
    try {
        const url = `${window.BilanStatistiquesConfig.routes.data}?from=${dateFrom.value}&to=${dateTo.value}`;
        const res = await fetch(url, {
            headers: { 'X-CSRF-TOKEN': getCsrf(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json() as { serie: SeriePoint[]; cartes: Cartes };
        serie.value  = data.serie;
        cartes.value = data.cartes;
        loadState.value = 'loaded';

        await nextTick();
        renderChart();
    } catch {
        loadState.value = 'error';
    }
}

watch([dateFrom, dateTo], () => load());
onMounted(() => load());

// ── Graphique ─────────────────────────────────────────────────────────────
const canvasRef = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

function renderChart(): void {
    if (!canvasRef.value) return;

    chart?.destroy();

    const labels = serie.value.map(p => fmtDateCourt(p.date));

    const config: ChartConfiguration<'line'> = {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Présence totale',
                    data: serie.value.map(p => p.totalPresence),
                    borderColor: '#0369a1',
                    backgroundColor: 'rgba(3,105,161,0.08)',
                    yAxisID: 'yPresence',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
                {
                    label: 'Montant collecté',
                    data: serie.value.map(p => p.totalMontant),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5,150,105,0.08)',
                    yAxisID: 'yMontant',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                yPresence: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Présence (personnes)' },
                },
                yMontant: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Montant (€)' },
                    ticks: { callback: (v) => fmtEuro(Number(v)) },
                },
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        title: (items: TooltipItem<'line'>[]) => {
                            const point = serie.value[items[0]?.dataIndex ?? 0];
                            return point ? fmtDateLabel(point.date) : '';
                        },
                        label: (item: TooltipItem<'line'>) => {
                            const point = serie.value[item.dataIndex];
                            if (!point) return '';

                            if (item.dataset.label === 'Présence totale') {
                                return [
                                    `Présence totale : ${point.totalPresence}`,
                                    `  · Présents : ${point.nbPresents}`,
                                    `  · En ligne : ${point.nbEnLigne}`,
                                    `Responsable mektaba : ${point.responsableMektaba ?? '—'}`,
                                ];
                            }

                            return [
                                `Montant total : ${fmtEuro(point.totalMontant)}`,
                                `  · Carte : ${fmtEuro(point.montantCarte)}`,
                                `  · Espèces : ${fmtEuro(point.montantEspece)}`,
                                `Responsable amana food : ${point.responsableAmanaFood ?? '—'}`,
                            ];
                        },
                    },
                },
            },
        },
    };

    chart = new Chart(canvasRef.value, config);
}

onUnmounted(() => chart?.destroy());
</script>

<template>
    <div class="flex flex-col gap-5">

        <!-- Plage de dates -->
        <div class="bg-white rounded-xl border border-surface-border shadow-sm px-5 py-4 flex flex-wrap items-center gap-3">
            <label for="stats_from" class="text-xs font-bold text-ink tracking-[0.2px]">📅 Du</label>
            <input
                id="stats_from" type="date" v-model="dateFrom" :max="dateTo"
                class="px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                       focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
            >
            <label for="stats_to" class="text-xs font-bold text-ink tracking-[0.2px]">Au</label>
            <input
                id="stats_to" type="date" v-model="dateTo" :min="dateFrom"
                class="px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                       focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
            >
            <span v-if="loadState === 'loaded'" class="text-[12.5px] text-ink-muted ml-auto">
                {{ cartes?.nbBilans ?? 0 }} bilan(s) sur la période
            </span>
        </div>

        <!-- Chargement / erreur -->
        <div v-if="loadState === 'loading'" class="text-center py-10 text-[13.5px] text-ink-muted">
            ⏳ Chargement des statistiques…
        </div>
        <div v-else-if="loadState === 'error'" class="text-center py-8 text-rose-600 text-[13px]">
            ❌ Erreur lors du chargement des statistiques.
        </div>

        <template v-else-if="loadState === 'loaded'">

            <!-- Aucune donnée -->
            <div
                v-if="!serie.length"
                class="text-center py-10 px-4 text-[13.5px] text-ink-muted bg-surface-2 rounded-lg border border-surface-border"
            >
                😕 Aucun bilan enregistré sur cette période.
            </div>

            <template v-else>
                <!-- Graphique -->
                <div class="bg-white rounded-xl border border-surface-border shadow-sm p-5">
                    <div class="relative h-[360px]">
                        <canvas ref="canvasRef"></canvas>
                    </div>
                </div>

                <!-- Cartes de stats -->
                <div v-if="cartes" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white rounded-xl border border-surface-border shadow-sm px-4 py-4">
                        <div class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.6px] mb-1">💰 Total collecté</div>
                        <div class="text-xl font-heading font-semibold text-ink">{{ fmtEuro(cartes.totalMontant) }}</div>
                    </div>

                    <div class="bg-white rounded-xl border border-surface-border shadow-sm px-4 py-4">
                        <div class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.6px] mb-1">👥 Présence moyenne</div>
                        <div class="text-xl font-heading font-semibold text-ink">{{ cartes.moyennePresence }}</div>
                    </div>

                    <div class="bg-white rounded-xl border border-surface-border shadow-sm px-4 py-4">
                        <div class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.6px] mb-1">🏆 Meilleure présence</div>
                        <div v-if="cartes.meilleureDate" class="text-xl font-heading font-semibold text-ink">
                            {{ cartes.meilleureDate.valeur }}
                            <div class="text-[11px] font-normal text-ink-muted mt-0.5">{{ fmtDateCourt(cartes.meilleureDate.date) }}</div>
                        </div>
                        <div v-else class="text-[13px] text-ink-muted">—</div>
                    </div>

                    <div class="bg-white rounded-xl border border-surface-border shadow-sm px-4 py-4">
                        <div class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.6px] mb-1">🥇 Meilleure collecte</div>
                        <div v-if="cartes.meilleureCollecte" class="text-xl font-heading font-semibold text-ink">
                            {{ fmtEuro(cartes.meilleureCollecte.valeur) }}
                            <div class="text-[11px] font-normal text-ink-muted mt-0.5">{{ fmtDateCourt(cartes.meilleureCollecte.date) }}</div>
                        </div>
                        <div v-else class="text-[13px] text-ink-muted">—</div>
                    </div>

                    <div class="bg-white rounded-xl border border-surface-border shadow-sm px-4 py-4">
                        <div class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.6px] mb-1">📋 Taux de remplissage</div>
                        <div class="text-xl font-heading font-semibold text-ink">
                            <template v-if="cartes.tauxRemplissage !== null">{{ cartes.tauxRemplissage }}%</template>
                            <template v-else>—</template>
                        </div>
                        <div class="text-[11px] font-normal text-ink-muted mt-0.5">{{ cartes.nbBilans }} / {{ cartes.nbCreneaux }} créneaux</div>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>
