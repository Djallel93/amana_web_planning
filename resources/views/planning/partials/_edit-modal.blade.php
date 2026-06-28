{{-- resources/views/planning/partials/_edit-modal.blade.php --}}
{{-- Modale de réassignation — uniquement incluse pour admin/gestionnaire --}}
<div class="fixed inset-0 bg-black/45 backdrop-blur-sm z-[400] flex items-center justify-center p-4
            opacity-0 pointer-events-none transition-opacity duration-200"
     id="editModalBackdrop"
     onclick="closeOnBackdrop(event)">

    <div class="bg-white rounded-2xl shadow-lg w-full max-w-sm transform scale-95 transition-transform duration-200"
         id="editModal">

        {{-- Header --}}
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0"
                 id="modalTitleIcon">✏️</div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1" id="modalTitle">Modifier l'assignation</span>
            <button onclick="closeModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-md text-ink-muted hover:bg-surface-3 hover:text-ink transition-colors bg-transparent border-0 cursor-pointer text-lg leading-none min-h-[44px] min-w-[44px]">
                ×
            </button>
        </div>

        {{-- Body --}}
        <div class="px-5 py-4 flex flex-col gap-4">

            {{-- Contexte --}}
            <div class="flex items-center gap-2 px-3 py-2.5 bg-sky-50 border border-sky-100 rounded-lg text-[13px]">
                <strong class="text-ink" id="modalContextDay">—</strong>
                <span class="text-ink-muted" id="modalContextTask">—</span>
            </div>

            {{-- Réassigner --}}
            <div>
                <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2">👤 Réassigner à</p>
                <div class="flex gap-2">
                    <select id="modalPersonSelect"
                            class="flex-1 px-3 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                   focus:border-accent focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] cursor-pointer">
                        <option value="">— Aucune personne (désassigner) —</option>
                    </select>
                    <button onclick="saveAssignation()" id="modalSaveBtn"
                            class="px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg
                                   shadow-[0_2px_10px_rgba(3,105,161,0.3)] transition-all cursor-pointer min-h-[44px] whitespace-nowrap">
                        Enregistrer
                    </button>
                </div>
            </div>

            {{-- Séparateur --}}
            <div class="h-px bg-surface-3"></div>

            {{-- Zone dangereuse --}}
            <div>
                <p class="text-[10.5px] font-bold text-rose-500 uppercase tracking-[0.7px] mb-2">⚠️ Zone dangereuse</p>
                <div class="flex gap-2 flex-wrap">
                    <button onclick="unassignTask()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                   bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100">
                        ✕ Désassigner
                    </button>
                    <button onclick="deleteCreneauFromModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[12.5px] font-semibold rounded-lg cursor-pointer transition-colors min-h-[44px]
                                   bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100">
                        🗑️ Supprimer le créneau
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
