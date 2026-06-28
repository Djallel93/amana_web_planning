{{-- resources/views/planning/partials/_add-creneau-modal.blade.php --}}
{{-- Modale d'ajout manuel d'un créneau — uniquement incluse pour admin/gestionnaire --}}
<div class="fixed inset-0 bg-black/45 backdrop-blur-sm z-[400] flex items-center justify-center p-4
            opacity-0 pointer-events-none transition-opacity duration-200"
     id="addCreneauBackdrop"
     onclick="closeAddCreneauOnBackdrop(event)">

    <div class="bg-white rounded-2xl shadow-lg w-full max-w-sm transform scale-95 transition-transform duration-200"
         id="addCreneauModal">

        {{-- Header --}}
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">➕</div>
            <span class="font-heading text-[14px] font-semibold text-ink flex-1">Ajouter un créneau</span>
            <button onclick="closeAddCreneauModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-md text-ink-muted hover:bg-surface-3 hover:text-ink transition-colors bg-transparent border-0 cursor-pointer text-lg leading-none min-h-[44px] min-w-[44px]">
                ×
            </button>
        </div>

        {{-- Body --}}
        <div class="px-5 py-4 flex flex-col gap-4">

            {{-- Contexte semaine --}}
            <div class="flex items-center gap-2 px-3 py-2.5 bg-sky-50 border border-sky-100 rounded-lg text-[13px]"
                 id="addCreneauWeekInfo">
                <strong class="text-ink">Semaine en cours</strong>
                <span class="text-ink-muted">Choisissez une date dans cette semaine</span>
            </div>

            {{-- Date --}}
            <div>
                <p class="text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] mb-2">📅 Date du créneau</p>
                <input type="date" id="addCreneauDate"
                       class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                              focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                <p class="text-[11.5px] text-ink-muted mt-1.5 min-h-[18px]" id="addCreneauHint"></p>
            </div>

            {{-- Actions --}}
            <div class="flex gap-2">
                <button onclick="submitAddCreneau()" id="addCreneauBtn"
                        class="flex-1 min-h-[48px] px-4 py-2.5 bg-accent hover:bg-accent-dark text-white text-[13px] font-bold rounded-lg
                               shadow-[0_3px_12px_rgba(3,105,161,0.3)] transition-all cursor-pointer flex items-center justify-center gap-1.5">
                    ➕ Créer le créneau
                </button>
                <button onclick="closeAddCreneauModal()"
                        class="px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors cursor-pointer min-h-[48px]">
                    Annuler
                </button>
            </div>

        </div>
    </div>
</div>
