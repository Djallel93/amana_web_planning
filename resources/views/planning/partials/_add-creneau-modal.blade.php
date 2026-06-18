{{-- resources/views/planning/partials/_add-creneau-modal.blade.php --}}
{{-- Modale d'ajout manuel d'un créneau — uniquement incluse pour admin/gestionnaire --}}
<div class="modal-backdrop" id="addCreneauBackdrop" onclick="closeAddCreneauOnBackdrop(event)">
    <div class="modal add-creneau-modal" id="addCreneauModal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="modal-title-icon" style="background:var(--emerald-bg);">➕</div>
                <span>Ajouter un créneau</span>
            </div>
            <button class="modal-close" onclick="closeAddCreneauModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-info" id="addCreneauWeekInfo">
                <strong>Semaine en cours</strong>
                <span>Choisissez une date dans cette semaine</span>
            </div>
            <div class="modal-section-title">📅 Date du créneau</div>
            <div>
                <input type="date" id="addCreneauDate" />
                <div class="add-creneau-hint" id="addCreneauHint"></div>
            </div>
            <div style="display:flex;gap:9px;margin-top:16px;">
                <button class="btn btn-primary" style="flex:1;justify-content:center;" onclick="submitAddCreneau()"
                    id="addCreneauBtn">
                    ➕ Créer le créneau
                </button>
                <button class="btn btn-secondary" onclick="closeAddCreneauModal()">Annuler</button>
            </div>
        </div>
    </div>
</div>