{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        /* ── Styles propres à la page d'inscription ──
            Tokens, reset, fonts, .flash sont fournis par base.css.
            .panel-*, .btn-submit, .form-group, .field-error sont fournis par auth.css.
             Ici : uniquement la mise en page spécifique (topbar, cartes, tableau dispo). */

        body {
            display: block; /* surcharge le flex de auth.css : ici layout vertical classique */
        }

        .topbar {
            background: var(--app-sidebar-bg);
            padding: 0 28px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-logo { display: flex; align-items: center; gap: 10px; }
        .topbar-logo-img { width: 28px; height: 28px; border-radius: 6px; object-fit: cover; }
        .topbar-logo-name { font-family: var(--font-heading); font-size: 15px; font-weight: 600; color: white; }
        .topbar-link {
            font-size: 12.5px;
            color: rgba(255,255,255,0.55);
            padding: 6px 14px;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius);
            transition: all 0.18s;
        }
        .topbar-link:hover { color: white; border-color: rgba(255,255,255,0.4); }

        .main { max-width: 720px; margin: 0 auto; padding: 40px 24px 64px; }
        .page-title { font-family: var(--font-heading); font-size: 26px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
        .page-sub { font-size: 13.5px; color: var(--ink-muted); margin-bottom: 32px; line-height: 1.65; }

        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 10px rgba(13,17,23,0.07);
            border: 1px solid var(--surface-border);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 22px;
            border-bottom: 1px solid var(--surface-3);
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }
        .card-header-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }
        .card-body { padding: 22px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        /* .form-group ici diffère légèrement de auth.css (gap au lieu de margin-bottom) :
           on garde la version locale pour ce formulaire à deux colonnes. */
        .form-grid .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 0; }

        input[type="text"], input[type="email"], input[type="tel"] {
            padding: 9px 13px; border: 1.5px solid var(--ink-faint); border-radius: var(--radius);
            font-size: 13.5px; font-family: var(--font-body); color: var(--ink); background: var(--surface);
            transition: all 0.18s; width: 100%; outline: none; -webkit-appearance: none; appearance: none;
        }
        input:focus { border-color: var(--app-accent); box-shadow: 0 0 0 3px var(--app-accent-glow); font-size: 16px; }

        .notice {
            background: var(--surface-2);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius);
            padding: 12px 16px;
            font-size: 13px;
            color: var(--ink-muted);
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.6;
        }

        .restrictions-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 380px; }
        .restrictions-table th {
            padding: 9px 10px; text-align: center; font-size: 10.5px; font-weight: 700;
            color: var(--ink-muted); text-transform: uppercase; letter-spacing: 0.7px;
            background: var(--surface-2); border-bottom: 1px solid var(--surface-3); font-family: var(--font-body);
        }
        .restrictions-table th.jour-header {
            background: var(--app-accent); color: white; font-size: 12px;
            text-transform: none; letter-spacing: 0; font-weight: 600;
        }
        .restrictions-table th.tache-col { text-align: left; padding-left: 16px; }
        .restrictions-table td { padding: 10px; text-align: center; border-bottom: 1px solid var(--surface-3); }
        .restrictions-table td:first-child { text-align: left; padding-left: 16px; font-weight: 600; color: var(--ink); }
        .restrictions-table tr:last-child td { border-bottom: none; }
        .restrictions-table tr:hover { background: var(--surface-2); }
        .restrictions-table input[type="checkbox"] {
            width: 16px; height: 16px; accent-color: var(--app-accent); cursor: pointer;
            -webkit-appearance: auto; appearance: auto;
        }

        .tache-chip { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .chip-entree     { background: #eff6ff; color: #2563eb; }
        .chip-mektaba    { background: #ecfdf5; color: #059669; }
        .chip-salle      { background: #fffbeb; color: #d97706; }
        .chip-amana_food { background: #fff1f2; color: #e11d48; }
        .chip-cours      { background: #f5f3ff; color: #7c3aed; }

        .restrictions-mobile { display: none; }
        .restriction-card { border: 1px solid var(--surface-border); border-radius: var(--radius); margin-bottom: 10px; overflow: hidden; }
        .restriction-card-header { padding: 10px 14px; background: var(--surface-2); font-weight: 700; font-size: 13px; color: var(--ink); }
        .restriction-card-body { padding: 10px 14px; display: flex; flex-direction: column; gap: 9px; }
        .restriction-day-row { display: flex; align-items: center; gap: 9px; font-size: 13px; color: var(--ink-light); }
        .restriction-day-row input[type="checkbox"] {
            width: 15px; height: 15px; accent-color: var(--app-accent); cursor: pointer; flex-shrink: 0;
            -webkit-appearance: auto; appearance: auto;
        }

        .submit-zone { display: flex; align-items: center; gap: 16px; margin-top: 8px; flex-wrap: wrap; }
        .submit-zone .btn-submit { width: auto; padding: 11px 28px; margin-bottom: 0; }
        .submit-note { font-size: 12px; color: var(--ink-muted); line-height: 1.55; }

        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

        @media (max-width: 680px) {
            .topbar { padding: 0 16px; }
            .topbar-logo-name { display: none; }
            .main { padding: 20px 16px 48px; }
            .page-title { font-size: 22px; }
            .form-grid { grid-template-columns: 1fr; gap: 13px; }
            .card-body { padding: 16px; }
            .restrictions-table-wrap { display: none; }
            .restrictions-mobile { display: block; }
            .submit-zone { flex-direction: column; align-items: stretch; }
            .submit-zone .btn-submit { width: 100%; text-align: center; }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <a href="{{ route('login') }}" class="topbar-logo">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="topbar-logo-img">
            <span class="topbar-logo-name">AMANA Planning</span>
        </a>
        <a href="{{ route('login') }}" class="topbar-link">← Connexion</a>
    </header>

    <div class="main">
        <h1 class="page-title">Rejoindre AMANA Planning</h1>
        <p class="page-sub">
            Remplissez ce formulaire pour soumettre votre candidature.<br>
            Un administrateur la validera et vous recevrez un email pour créer votre mot de passe.
        </p>

        @if($errors->any())
            <div class="flash flash-error">
                ❌ Veuillez corriger les erreurs ci-dessous avant de soumettre.
            </div>
        @endif

        <form action="{{ route('inscription.submit') }}" method="POST">
            @csrf

            {{-- Informations personnelles --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:#f5f3ff;">👤</div>
                    Informations personnelles
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prenom">Prénom <span class="req">*</span></label>
                            <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" required maxlength="100" placeholder="Votre prénom">
                            @error('prenom')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nom">Nom <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="100" placeholder="Votre nom de famille">
                            @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="email">Adresse email <span class="req">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="votre@email.fr">
                            <span class="form-hint">Ce sera votre identifiant de connexion</span>
                            @error('email')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" maxlength="20" placeholder="+33 6 00 00 00 00">
                            @error('telephone')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Disponibilités --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:#ecfdf5;">📋</div>
                    Mes disponibilités par tâche
                </div>
                <div class="card-body">
                    <div class="notice">
                        <span style="font-size:16px;flex-shrink:0;">ℹ️</span>
                        <span>Cochez les tâches que vous <strong>pouvez effectuer</strong> chaque jour. Vous pourrez modifier ces disponibilités à tout moment depuis votre espace.</span>
                    </div>

                    {{-- Desktop table --}}
                    <div class="restrictions-table-wrap table-scroll">
                        <table class="restrictions-table">
                            <thead>
                                <tr>
                                    <th class="tache-col" rowspan="2" style="vertical-align:middle;">Tâche</th>
                                    @foreach($jours as $jour)
                                        <th class="jour-header">{{ $jour }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($taches as $tache)
                                    <tr>
                                        <td>
                                            <span class="tache-chip chip-{{ $tache->code }}">{{ $tache->libelle }}</span>
                                        </td>
                                        @foreach($jours as $jour)
                                            <td>
                                                <input type="checkbox"
                                                    name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                    value="1"
                                                    title="{{ $tache->libelle }} — {{ $jour }}"
                                                    {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile cards --}}
                    <div class="restrictions-mobile">
                        @foreach($taches as $tache)
                            <div class="restriction-card">
                                <div class="restriction-card-header">
                                    <span class="tache-chip chip-{{ $tache->code }}">{{ $tache->libelle }}</span>
                                </div>
                                <div class="restriction-card-body">
                                    @foreach($jours as $jour)
                                        <div class="restriction-day-row">
                                            <input type="checkbox"
                                                id="mob_{{ $tache->id }}_{{ $jour }}"
                                                name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                value="1"
                                                {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}>
                                            <label for="mob_{{ $tache->id }}_{{ $jour }}">{{ $jour }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="submit-zone">
                <button type="submit" class="btn-submit">✉️ Soumettre ma candidature</button>
                <p class="submit-note">
                    En soumettant ce formulaire, vous acceptez que vos informations<br>
                    soient utilisées dans le cadre du bénévolat AMANA.
                </p>
            </div>
        </form>
    </div>
</body>
</html>