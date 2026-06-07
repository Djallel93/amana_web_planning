{{-- resources/views/admin/candidatures/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Candidatures — AMANA')

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">Candidatures en attente</div>
            <div class="page-subtitle">Validez ou refusez les demandes d'inscription</div>
        </div>
    </div>

    <div class="stat-grid" style="margin-bottom:22px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
        <div class="stat-card color-amber">
            <div class="stat-value" style="color:var(--amber);">{{ $candidatures->count() }}</div>
            <div class="stat-label">En attente</div>
        </div>
    </div>

    @if($candidatures->isEmpty())
        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">✅</div>
                <div class="empty-title">Aucune candidature en attente</div>
                <div class="empty-desc">
                    Toutes les candidatures ont été traitées.<br>
                    Les nouvelles inscriptions apparaîtront ici automatiquement.
                </div>
            </div>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:14px;">
            @foreach($candidatures as $candidat)
                <div class="card">
                    <div style="padding:20px 22px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

                            {{-- Identity --}}
                            <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:200px;">
                                <div
                                    style="width:44px;height:44px;flex-shrink:0;background:var(--app-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;font-weight:700;">
                                    {{ strtoupper(substr($candidat->prenom, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-family:var(--font-heading);font-size:15px;font-weight:600;color:var(--ink);">
                                        {{ $candidat->prenom }} {{ strtoupper($candidat->nom) }}
                                    </div>
                                    <div style="font-size:13px;color:var(--ink-muted);margin-top:2px;">{{ $candidat->email }}</div>
                                    @if($candidat->telephone)
                                        <div style="font-size:12px;color:var(--ink-muted);">📞 {{ $candidat->telephone }}</div>
                                    @endif
                                </div>
                            </div>

                            {{-- Info --}}
                            <div style="display:flex;flex-direction:column;gap:5px;min-width:180px;">
                                <div
                                    style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--ink-muted);">
                                    Informations</div>
                                <div style="font-size:12.5px;color:var(--ink-light);">
                                    📅 Inscrit le :
                                    {{ $candidat->date_inscription_benevole
                        ? $candidat->date_inscription_benevole->locale('fr')->isoFormat('D MMM YYYY')
                        : '—' }}
                                </div>
                                @if($candidat->vehicule)
                                    <div style="font-size:12.5px;color:var(--ink-light);">
                                        🚗 {{ $candidat->vehicule->type }} ({{ $candidat->vehicule->capacite_kg }} kg)
                                    </div>
                                @endif
                                <div style="font-size:12.5px;color:var(--ink-light);">
                                    🕐 {{ $candidat->derniere_maj?->locale('fr')->isoFormat('D MMM YYYY à HH:mm') ?? '—' }}
                                </div>
                            </div>

                            {{-- Disponibilités --}}
                            <div style="min-width:200px;">
                                <div
                                    style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:var(--ink-muted);margin-bottom:8px;">
                                    Disponibilités</div>
                                @if($candidat->restrictions->isEmpty())
                                    <span style="font-size:12px;color:var(--ink-faint);font-style:italic;">Non renseignées</span>
                                @else
                                    <div style="display:flex;flex-direction:column;gap:5px;">
                                        @foreach(['Vendredi', 'Samedi'] as $jour)
                                            @php $restrictionsDuJour = $candidat->restrictions->where('jour', $jour); @endphp
                                            @if($restrictionsDuJour->isNotEmpty())
                                                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                    <span
                                                        style="font-size:11.5px;font-weight:600;color:var(--ink-muted);width:55px;">{{ $jour }}</span>
                                                    @foreach($restrictionsDuJour as $r)
                                                        <span
                                                            style="display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:600;background:{{ $r->autorise ? 'var(--emerald-bg)' : 'var(--rose-bg)' }};color:{{ $r->autorise ? '#065f46' : '#9f1239' }};border:1px solid {{ $r->autorise ? 'var(--emerald-border)' : 'var(--rose-border)' }};">
                                                            {{ $r->autorise ? '✓' : '✗' }} {{ $r->tache->libelle ?? '—' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div style="display:flex;flex-direction:column;gap:9px;flex-shrink:0;min-width:190px;">

                                {{-- Validate with role selector --}}
                                <form action="{{ route('admin.candidatures.valider', $candidat->id) }}" method="POST"
                                    onsubmit="return confirmValidation(this)">
                                    @csrf
                                    <div style="margin-bottom:8px;">
                                        <label
                                            style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--ink-muted);display:block;margin-bottom:5px;">
                                            Rôle attribué
                                        </label>
                                        <select name="role"
                                            style="width:100%;padding:7px 11px;border:1.5px solid var(--ink-faint);border-radius:var(--radius);font-size:13px;font-family:var(--font-body);color:var(--ink);background:var(--surface);outline:none;-webkit-appearance:none;appearance:none;">
                                            @foreach($roles as $r)
                                                @php
                                                    $roleLabels = ['admin' => '🛡️ Administrateur', 'gestionnaire' => '⚙️ Gestionnaire', 'membre' => '👤 Membre'];
                                                @endphp
                                                <option value="{{ $r->code }}" {{ $r->code === 'membre' ? 'selected' : '' }}>
                                                    {{ $roleLabels[$r->code] ?? $r->libelle }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;">
                                        ✅ Valider
                                    </button>
                                </form>

                                {{-- Refuse --}}
                                <form action="{{ route('admin.candidatures.refuser', $candidat->id) }}" method="POST"
                                    onsubmit="return confirm('Refuser la candidature de {{ $candidat->prenom }} {{ $candidat->nom }} ?')">
                                    @csrf
                                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;">
                                        ✕ Refuser
                                    </button>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        function confirmValidation(form) {
            const sel = form.querySelector('select[name="role"]');
            const label = sel.options[sel.selectedIndex].text.trim();
            return confirm(`Valider cette candidature avec le rôle "${label}" ?\nUn email d'invitation sera envoyé.`);
        }
    </script>
@endpush