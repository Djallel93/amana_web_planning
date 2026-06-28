{{-- resources/views/planning/export.blade.php --}}
@extends('layouts.app')

@section('title', 'Export PDF — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">Export PDF</h1>
        <p class="text-[13px] text-ink-muted mt-1">Générez un PDF du planning sur une plage de dates</p>
    </div>
    <a href="{{ route('planning.index') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 border-[1.5px] border-ink-faint text-ink-muted hover:bg-surface-3 hover:text-ink text-[13px] font-semibold rounded-lg transition-colors no-underline min-h-[44px]">
        ← Retour
    </a>
</div>

<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 bg-rose-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📄</div>
            <span class="font-heading text-[14px] font-semibold text-ink">Paramètres d'export</span>
        </div>
        <div class="p-5">
            <form action="{{ route('planning.export.pdf') }}" method="POST" target="_blank">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="date_debut" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Date de début <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" id="date_debut" name="date_debut"
                               value="{{ old('date_debut', now()->startOfMonth()->toDateString()) }}" required
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                        @error('date_debut')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="date_fin" class="text-xs font-bold text-ink tracking-[0.2px]">
                            Date de fin <span class="text-rose-500">*</span>
                        </label>
                        <input type="date" id="date_fin" name="date_fin"
                               value="{{ old('date_fin', now()->endOfMonth()->toDateString()) }}" required
                               class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                      focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                        @error('date_fin')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="flex items-start gap-2.5 bg-surface-2 border border-surface-border rounded-lg px-4 py-3.5 mb-5 text-[12.5px] text-ink-muted">
                    <span class="text-base flex-shrink-0 mt-px">📋</span>
                    <span>Le PDF généré contiendra les créneaux du planning dans la plage sélectionnée, avec les assignations de tâches par jour (format A4 paysage).</span>
                </div>

                <button type="submit"
                        class="w-full min-h-[48px] px-5 py-3 bg-rose-600 hover:bg-rose-700 text-white font-bold text-[13.5px] rounded-lg
                               shadow-[0_3px_14px_rgba(220,38,38,0.3)] hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer
                               flex items-center justify-center gap-2">
                    📄 Générer et télécharger le PDF
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.getElementById('date_debut').addEventListener('change', function () {
        const fin = document.getElementById('date_fin');
        if (!fin.value || fin.value < this.value) fin.value = this.value;
        fin.min = this.value;
    });
</script>
@endpush
