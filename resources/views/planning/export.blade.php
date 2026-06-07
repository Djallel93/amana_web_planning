{{-- resources/views/planning/export.blade.php --}}
@extends('layouts.app')

@section('title', 'Export PDF — AMANA')

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Export PDF</div>
            <div class="page-subtitle">Générez un PDF du planning sur une plage de dates</div>
        </div>
        <a href="{{ route('planning.index') }}" class="btn btn-secondary">← Retour</a>
    </div>

    <div style="max-width:520px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon" style="background:var(--rose-bg);">📄</div>
                    Paramètres d'export
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('planning.export.pdf') }}" method="POST" target="_blank">
                    @csrf
                    <div class="form-grid" style="margin-bottom:18px;">
                        <div class="form-group">
                            <label for="date_debut">Date de début <span class="req">*</span></label>
                            <input type="date" id="date_debut" name="date_debut"
                                value="{{ old('date_debut', now()->startOfMonth()->toDateString()) }}" required>
                            @error('date_debut')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="date_fin">Date de fin <span class="req">*</span></label>
                            <input type="date" id="date_fin" name="date_fin"
                                value="{{ old('date_fin', now()->endOfMonth()->toDateString()) }}" required>
                            @error('date_fin')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div
                        style="background:var(--surface-2);border-radius:var(--radius);padding:13px 16px;margin-bottom:20px;font-size:13px;color:var(--ink-muted);display:flex;align-items:flex-start;gap:10px;">
                        <span style="font-size:15px;flex-shrink:0;">📋</span>
                        <span>Le PDF généré contiendra les créneaux du planning dans la plage sélectionnée, avec les
                            assignations de tâches par jour (format A4 paysage).</span>
                    </div>

                    <button type="submit" class="btn btn-danger btn-lg" style="width:100%;justify-content:center;">
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