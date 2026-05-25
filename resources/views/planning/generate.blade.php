{{-- resources/views/planning/generate.blade.php --}}
@extends('layouts.app')

@section('title', 'Générer le planning — AMANA')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">✨ Générer le planning</div>
        <div class="page-subtitle">Génération automatique par rotation des tâches</div>
    </div>
    <a href="{{ route('planning.index') }}" class="btn btn-secondary">← Retour au planning</a>
</div>

<div style="max-width: 560px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚙️ Paramètres de génération</span>
        </div>

        <div class="card" style="background:#ebf8ff; border: 1px solid #bee3f8; box-shadow:none; padding:14px 18px; margin-bottom:20px;">
            <strong style="color:#2c5282;">ℹ️ Comment ça marche</strong>
            <ul style="margin-top:8px; padding-left:20px; color:#2c5282; font-size:13px; line-height:1.8;">
                <li>Le système trouve le premier <strong>vendredi</strong> à partir de la date choisie</li>
                <li>Génère <strong>vendredi + samedi</strong> pour chaque semaine</li>
                <li><strong>amana_food</strong> : rotation stricte par cycle global</li>
                <li><strong>entree, mektaba, salle</strong> : score d'équilibrage adaptatif</li>
                <li>Les créneaux existants à partir de cette date seront <strong>remplacés</strong></li>
            </ul>
        </div>

        <form action="{{ route('planning.generate') }}" method="POST" id="generateForm">
            @csrf

            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="date_debut">
                        📆 Date de début
                        <span class="required">*</span>
                    </label>
                    <input
                        type="date"
                        id="date_debut"
                        name="date_debut"
                        value="{{ old('date_debut', now()->toDateString()) }}"
                        min="{{ now()->toDateString() }}"
                        required
                    >
                    <span class="form-hint">Le prochain vendredi sera automatiquement trouvé</span>
                    @error('date_debut')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="semaines">
                        📊 Nombre de semaines
                        <span class="required">*</span>
                    </label>
                    <input
                        type="number"
                        id="semaines"
                        name="semaines"
                        value="{{ old('semaines', 4) }}"
                        min="1"
                        max="52"
                        required
                    >
                    <span class="form-hint">Chaque semaine = vendredi + samedi</span>
                    @error('semaines')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Aperçu du calcul --}}
            <div id="preview" style="
                background:#f7fafc;
                border:1px solid #e2e8f0;
                border-radius:8px;
                padding:14px;
                margin: 20px 0;
                font-size:13px;
                color:#4a5568;
            ">
                <strong>Aperçu :</strong>
                <span id="previewText">Remplissez les champs pour voir l'aperçu</span>
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                style="width:100%; justify-content:center; padding:13px;"
                id="submitBtn"
            >
                ✨ Générer le planning
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Aperçu dynamique des dates générées
    function updatePreview() {
        const dateInput = document.getElementById('date_debut').value;
        const semaines  = parseInt(document.getElementById('semaines').value) || 0;

        if (!dateInput || semaines < 1) {
            document.getElementById('previewText').textContent = 'Remplissez les champs pour voir l\'aperçu';
            return;
        }

        // Trouver le prochain vendredi
        const date = new Date(dateInput + 'T00:00:00');
        while (date.getDay() !== 5) { date.setDate(date.getDate() + 1); }

        const fin = new Date(date);
        fin.setDate(fin.getDate() + (semaines - 1) * 7 + 1); // +1 pour le samedi

        const opts = { day: 'numeric', month: 'long', year: 'numeric', locale: 'fr-FR' };
        const fmt  = d => d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });

        document.getElementById('previewText').innerHTML =
            `<strong>${semaines * 2} créneaux</strong> (${semaines} vendredis + ${semaines} samedis) ` +
            `du <strong>${fmt(date)}</strong> au <strong>${fmt(fin)}</strong>`;
    }

    document.getElementById('date_debut').addEventListener('change', updatePreview);
    document.getElementById('semaines').addEventListener('input', updatePreview);
    updatePreview();

    // Désactiver le bouton à la soumission
    document.getElementById('generateForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = '⏳ Génération en cours...';
        btn.style.opacity = '0.7';
    });
</script>
@endpush
