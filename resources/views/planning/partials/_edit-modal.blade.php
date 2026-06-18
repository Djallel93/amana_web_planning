{{-- resources/views/planning/partials/_edit-modal.blade.php --}}
{{-- Modale de réassignation — uniquement incluse pour admin/gestionnaire --}}
<div class="modal-backdrop" id="editModalBackdrop" onclick="closeOnBackdrop(event)">
    <div class="modal" id="editModal">
        <div class="modal-header">
            <div class="modal-title">
                <div class="modal-title-icon" id="modalTitleIcon" style="background:var(--sky-bg);">✏️</div>
                <span id="modalTitle">Modifier l'assignation</span>
            </div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-info">
                <strong id="modalContextDay">—</strong>
                <span id="modalContextTask">—</span>
            </div>
            <div class="modal-section-title">👤 Réassigner à</div>
            <div class="person-select-wrap">
                <select id="modalPersonSelect">
                    <option value="">— Aucune personne (désassigner) —</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="saveAssignation()"
                    id="modalSaveBtn">Enregistrer</button>
            </div>
            <div class="divider"></div>
            <div class="modal-section-title" style="color:var(--rose);">⚠️ Zone dangereuse</div>
            <div style="display:flex;gap:9px;flex-wrap:wrap;">
                <button class="btn btn-danger btn-sm" onclick="unassignTask()">✕ Désassigner</button>
                <button class="btn btn-danger btn-sm" onclick="deleteCreneauFromModal()">🗑️ Supprimer le
                    créneau</button>
            </div>
        </div>
    </div>
</div>