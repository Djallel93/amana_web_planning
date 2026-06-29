// public/js/planning-index.js
// Logique de la vue Planning (resources/views/planning/index.blade.php).
// Extrait du <script> inline pour fonctionner sans build npm sur IONOS.
//
// CSRF et routes nommées ne peuvent pas vivre dans un fichier statique
// (elles dépendent de Blade) — elles sont injectées par index.blade.php
// via window.PlanningConfig avant le chargement de ce fichier.

const CSRF = window.PlanningConfig.csrf;
const ROUTES = window.PlanningConfig.routes;

let personnesCache = null;
let currentCell = null;
const activeYears = new Set();
const activeMonths = new Set();

document.addEventListener('DOMContentLoaded', function () {
    const fd = window.PlanningFilterDefaults;

    // Activer l'année courante par défaut
    const yearChip = document.querySelector(
        '.filter-chip[data-type="year"][data-value="' + fd.currentYear + '"]'
    );
    if (yearChip) { yearChip.classList.add('active'); activeYears.add(fd.currentYear); }

    // Activer mois-1, mois courant, mois+1
    [fd.previousMonth, fd.currentMonth, fd.nextMonth].forEach(function (m) {
        const chip = document.querySelector(
            '.filter-chip[data-type="month"][data-value="' + m + '"]'
        );
        if (chip) { chip.classList.add('active'); activeMonths.add(m); }
    });

    // Si mois-1 ou mois+1 appartient à une autre année, on l'ajoute aussi
    // aux années actives pour que ces semaines restent visibles.
    if (fd.previousMonthYear !== fd.currentYear) activeYears.add(fd.previousMonthYear);
    if (fd.nextMonthYear !== fd.currentYear) activeYears.add(fd.nextMonthYear);

    applyFilters();
});

/* ══ FILTERS ══════════════════════════════════════════════════════ */
function toggleFilter(chip) {
    const type = chip.dataset.type;
    const value = parseInt(chip.dataset.value);
    const set = type === 'year' ? activeYears : activeMonths;
    if (set.has(value)) { set.delete(value); chip.classList.remove('active'); }
    else { set.add(value); chip.classList.add('active'); }
    applyFilters();
}

function clearFilters() {
    activeYears.clear(); activeMonths.clear();
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    applyFilters();
}

function applyFilters() {
    let visible = 0;
    document.querySelectorAll('.week-block').forEach(function (block) {
        const blockYear = parseInt(block.dataset.year);
        const blockMonth = parseInt(block.dataset.month);

        const yearOk = activeYears.size === 0 || activeYears.has(blockYear);
        const monthOk = activeMonths.size === 0 || activeMonths.has(blockMonth);

        // Une semaine est visible si elle passe BOTH les filtres actifs.
        // Cas particulier : si une année hors de l'année courante est activée
        // uniquement à cause d'un mois de bord (jan/déc), on filtre aussi sur
        // le mois pour ne pas afficher toute cette année.
        const show = yearOk && monthOk;

        block.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const el = document.getElementById('resultsCount');
    if (el) el.textContent = (activeYears.size || activeMonths.size)
        ? visible + ' semaine' + (visible !== 1 ? 's' : '') + ' affichée' + (visible !== 1 ? 's' : '')
        : '';
}

/* ══ LOAD PERSONNES ═══════════════════════════════════════════════ */
async function loadPersonnes() {
    if (personnesCache) return personnesCache;
    const res = await fetch(ROUTES.personnes, {
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    });
    personnesCache = await res.json();
    return personnesCache;
}

/* ══ EDIT / ASSIGN MODAL ══════════════════════════════════════════ */
async function openEditModal(td) {
    currentCell = td;
    const code = td.dataset.tacheCode;
    const label = td.dataset.tacheLabel;

    const colors = {
        entree: { bg: '#eff6ff', icon: '🚪' },
        mektaba: { bg: '#ecfdf5', icon: '📚' },
        salle: { bg: '#fffbeb', icon: '🏛️' },
        amana_food: { bg: '#fff1f2', icon: '🥪' },
        cours: { bg: '#f5f3ff', icon: '🎓' },
    };

    const c = colors[code] || { bg: 'var(--sky-bg)', icon: '✏️' };
    document.getElementById('modalTitleIcon').style.background = c.bg;
    document.getElementById('modalTitleIcon').textContent = c.icon;
    document.getElementById('modalTitle').textContent = `Modifier — ${label}`;
    document.getElementById('modalContextDay').textContent = `${td.dataset.jour} ${td.dataset.date}`;
    document.getElementById('modalContextTask').textContent = `Tâche : ${label}`;

    const select = document.getElementById('modalPersonSelect');
    select.innerHTML = '<option value="">— Aucune personne (désassigner) —</option>';
    const personnes = await loadPersonnes();
    personnes.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id; opt.textContent = p.label;
        select.appendChild(opt);
    });

    const chip = document.getElementById(`chip-${td.dataset.creneauId}-${code}`);
    if (chip && !chip.classList.contains('tache-vide')) {
        const name = chip.textContent.trim();
        for (const opt of select.options) {
            if (opt.textContent.trim() === name) { opt.selected = true; break; }
        }
    }

    const bd = document.getElementById('editModalBackdrop');
    bd.classList.remove('opacity-0', 'pointer-events-none');
    bd.classList.add('opacity-100');
    bd.querySelector('#editModal').classList.remove('scale-95');
    bd.querySelector('#editModal').classList.add('scale-100');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const bd = document.getElementById('editModalBackdrop');
    if (!bd) return;
    bd.classList.add('opacity-0', 'pointer-events-none');
    bd.classList.remove('opacity-100');
    bd.querySelector('#editModal').classList.add('scale-95');
    bd.querySelector('#editModal').classList.remove('scale-100');
    document.body.style.overflow = '';
    currentCell = null;
}

function closeOnBackdrop(e) {
    if (e.target === document.getElementById('editModalBackdrop')) closeModal();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeAddCreneauModal(); }
});

async function saveAssignation() {
    if (!currentCell) return;
    const creneauId = currentCell.dataset.creneauId;
    const tacheId = currentCell.dataset.tacheId;
    const personneId = document.getElementById('modalPersonSelect').value || null;
    const btn = document.getElementById('modalSaveBtn');
    btn.disabled = true; btn.textContent = '…';
    try {
        const res = await fetch(`${ROUTES.assignation}/${creneauId}/tache/${tacheId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ id_personne: personneId ? parseInt(personneId) : null }),
        });
        const data = await res.json();
        if (data.success) { updateCell(currentCell, data.personne); showToast(data.message, 'success'); closeModal(); }
        else showToast('Erreur lors de la mise à jour', 'error');
    } catch { showToast('Erreur réseau', 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Enregistrer'; }
}

async function unassignTask() {
    if (!currentCell) return;
    if (!confirm('Désassigner cette tâche ?')) return;
    const creneauId = currentCell.dataset.creneauId;
    const tacheId = currentCell.dataset.tacheId;
    try {
        const res = await fetch(`${ROUTES.assignation}/${creneauId}/tache/${tacheId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.success) { updateCell(currentCell, null); showToast('Tâche désassignée', 'success'); closeModal(); }
    } catch { showToast('Erreur réseau', 'error'); }
}

async function deleteCreneau(id, el) {
    if (!confirm('Supprimer ce créneau et toutes ses tâches ?')) return;
    await doDeleteCreneau(id, el);
}

async function deleteCreneauFromModal() {
    if (!currentCell) return;
    const id = parseInt(currentCell.dataset.creneauId);
    if (!confirm('Supprimer tout ce créneau ?')) return;
    closeModal();
    await doDeleteCreneau(id, null);
}

async function doDeleteCreneau(id, el) {
    if (el) { el.disabled = true; el.textContent = '…'; }
    try {
        const res = await fetch(`${ROUTES.creneau}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const data = await res.json();
        if (data.success) {
            const row = document.getElementById(`row-creneau-${id}`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); checkEmptyWeeks(); }, 300);
            }
            showToast(data.message, 'success');
        } else showToast('Erreur', 'error');
    } catch {
        showToast('Erreur réseau', 'error');
        if (el) { el.disabled = false; el.textContent = '🗑️'; }
    }
}

async function deleteWeek(ids, el) {
    if (!confirm(`Supprimer les ${ids.length} créneaux de cette semaine ?`)) return;
    el.disabled = true; el.innerHTML = '⏳ Suppression…';
    let n = 0;
    for (const id of ids) {
        try {
            const res = await fetch(`${ROUTES.creneau}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) { n++; document.getElementById(`row-creneau-${id}`)?.remove(); }
        } catch { }
    }
    checkEmptyWeeks();
    showToast(`Semaine supprimée (${n} créneaux)`, 'success');
}

function updateCell(td, personne) {
    const code = td.dataset.tacheCode;
    const chip = document.getElementById(`chip-${td.dataset.creneauId}-${code}`);
    if (!chip) return;
    if (personne) {
        chip.className = `chip-${code} inline-flex items-center px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold`;
        chip.textContent = personne.label;
    } else {
        chip.className = 'tache-vide text-ink-faint italic text-xs';
        chip.textContent = '—';
    }
}

function checkEmptyWeeks() {
    document.querySelectorAll('.week-block').forEach(block => {
        if (block.querySelectorAll('tbody tr').length === 0) {
            block.style.transition = 'opacity 0.4s';
            block.style.opacity = '0';
            setTimeout(() => block.remove(), 400);
        }
    });
}

/* ══ ADD CRÉNEAU MODAL ════════════════════════════════════════════ */
let addCreneauMin = '', addCreneauMax = '', addCreneauExisting = [];

function openAddCreneauModal(weekMin, weekMax, existingDates) {
    addCreneauMin = weekMin;
    addCreneauMax = weekMax;
    addCreneauExisting = existingDates || [];

    const infoEl = document.getElementById('addCreneauWeekInfo');
    if (infoEl) {
        const fmtMin = new Date(weekMin + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
        const fmtMax = new Date(weekMax + 'T00:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
        infoEl.innerHTML = `<strong>Semaine du ${fmtMin} au ${fmtMax}</strong>`;
    }

    const dateInput = document.getElementById('addCreneauDate');
    dateInput.min = weekMin;
    dateInput.max = weekMax;
    dateInput.value = '';

    const hint = document.getElementById('addCreneauHint');
    if (addCreneauExisting.length > 0) {
        const labels = addCreneauExisting.map(d => {
            const dt = new Date(d + 'T00:00:00');
            return dt.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
        });
        hint.textContent = `Déjà créé : ${labels.join(', ')}.`;
    } else {
        hint.textContent = 'Choisissez n\'importe quel jour de cette semaine.';
    }

    const bd2 = document.getElementById('addCreneauBackdrop');
    bd2.classList.remove('opacity-0', 'pointer-events-none');
    bd2.classList.add('opacity-100');
    bd2.querySelector('#addCreneauModal').classList.remove('scale-95');
    bd2.querySelector('#addCreneauModal').classList.add('scale-100');
    document.body.style.overflow = 'hidden';
    setTimeout(() => dateInput.focus(), 220);
}

function closeAddCreneauModal() {
    const bd2 = document.getElementById('addCreneauBackdrop');
    if (!bd2) return;
    bd2.classList.add('opacity-0', 'pointer-events-none');
    bd2.classList.remove('opacity-100');
    bd2.querySelector('#addCreneauModal').classList.add('scale-95');
    bd2.querySelector('#addCreneauModal').classList.remove('scale-100');
    document.body.style.overflow = '';
}

function closeAddCreneauOnBackdrop(e) {
    if (e.target === document.getElementById('addCreneauBackdrop')) closeAddCreneauModal();
}

async function submitAddCreneau() {
    const dateInput = document.getElementById('addCreneauDate');
    const date = dateInput.value;

    if (!date) { dateInput.focus(); showToast('Veuillez choisir une date.', 'error'); return; }
    if (addCreneauExisting.includes(date)) { showToast('Un créneau existe déjà pour cette date.', 'error'); dateInput.focus(); return; }

    const btn = document.getElementById('addCreneauBtn');
    btn.disabled = true; btn.textContent = '⏳ Création…';

    try {
        const res = await fetch(ROUTES.creneau, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ date }),
        });
        const data = await res.json();
        if (res.ok && data.success) {
            showToast(data.message, 'success');
            closeAddCreneauModal();
            setTimeout(() => window.location.reload(), 700);
        } else {
            const msg = data.errors?.date?.[0] || data.message || 'Erreur lors de la création.';
            showToast(msg, 'error');
            btn.disabled = false; btn.textContent = '➕ Créer le créneau';
        }
    } catch {
        showToast('Erreur réseau', 'error');
        btn.disabled = false; btn.textContent = '➕ Créer le créneau';
    }
}

/* ══ TOASTS ═══════════════════════════════════════════════════════ */
function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => {
        t.style.animation = 'toastOut 0.3s ease forwards';
        setTimeout(() => t.remove(), 300);
    }, 3200);
}