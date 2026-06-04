{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>

    {{-- Normalize.css --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:     #4f46e5;
            --violet:      #7c3aed;
            --ink:         #0f1117;
            --ink-light:   #3d4151;
            --ink-muted:   #7a7f94;
            --ink-faint:   #c4c8d8;
            --surface:     #ffffff;
            --surface-2:   #f4f5f9;
            --surface-3:   #eceef5;
            --border:      #c4c8d8;
            --rose:        #e11d48;
            --emerald:     #059669;
            --amber:       #d97706;
            --amber-bg:    #fffbeb;
            --radius:      10px;
            --radius-lg:   16px;
            --shadow:      0 4px 12px rgba(15,17,23,0.08);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: var(--surface-2);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0; padding: 0;
        }

        img, svg { display: block; max-width: 100%; }
        button { cursor: pointer; font-family: inherit; }
        a { color: inherit; text-decoration: none; }

        /* ── Header ── */
        .header {
            background: var(--ink);
            padding: 0 32px;
            height: 56px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 100;
        }
        .header-logo {
            display: flex; align-items: center; gap: 10px; text-decoration: none;
        }
        .header-logo-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--violet));
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center; font-size: 15px;
            flex-shrink: 0;
        }
        .header-logo-name {
            font-family: 'DM Serif Display', serif; font-size: 16px; color: white;
        }
        .header-login {
            font-size: 13px; color: rgba(255,255,255,0.6); text-decoration: none;
            padding: 6px 14px; border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius); transition: all 0.18s;
            white-space: nowrap;
        }
        .header-login:hover { color: white; border-color: rgba(255,255,255,0.5); }

        /* ── Main ── */
        .main {
            max-width: 780px; margin: 0 auto;
            padding: 40px 24px 60px;
        }
        .page-title {
            font-family: 'DM Serif Display', serif;
            font-size: 28px; color: var(--ink); margin-bottom: 6px;
        }
        .page-sub {
            font-size: 14px; color: var(--ink-muted);
            margin-bottom: 32px; line-height: 1.6;
        }

        /* ── Flash ── */
        .flash {
            padding: 13px 18px; border-radius: var(--radius);
            margin-bottom: 22px; font-size: 13.5px; font-weight: 500;
            border: 1px solid; display: flex; align-items: center; gap: 10px;
        }
        .flash-error { background:#fff1f2; border-color:#fecdd3; color:#9f1239; }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden; margin-bottom: 20px;
        }
        .card-header {
            display: flex; align-items: center; gap: 10px;
            padding: 16px 22px; border-bottom: 1px solid var(--surface-3);
            font-size: 15px; font-weight: 700; color: var(--ink);
        }
        .card-header-icon {
            width: 30px; height: 30px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .card-body { padding: 24px; }

        /* ── Form ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        label {
            font-size: 12.5px; font-weight: 700;
            color: var(--ink); letter-spacing: 0.2px; display: block;
        }
        label .req { color: var(--rose); margin-left: 2px; }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="tel"],
        select {
            padding: 9px 13px;
            border: 1.5px solid var(--ink-faint);
            border-radius: var(--radius);
            font-size: 13.5px; font-family: inherit;
            color: var(--ink); background: var(--surface);
            transition: all 0.18s; width: 100%; outline: none;
            -webkit-appearance: none; appearance: none;
        }
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
            font-size: 16px; /* prevent iOS zoom */
        }
        /* Date input keeps its own appearance on mobile for the picker */
        input[type="date"] { -webkit-appearance: auto; appearance: auto; }
        input[type="date"]:focus { font-size: 13.5px; }

        .form-hint  { font-size: 12px; color: var(--ink-muted); }
        .form-error { color: var(--rose); font-size: 12px; }

        /* ── Info box ── */
        .restrictions-info {
            background: var(--amber-bg);
            border: 1px solid #fde68a;
            border-radius: var(--radius);
            padding: 12px 16px;
            font-size: 13px; color: #92400e;
            margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px;
        }

        /* ── Restrictions table ── */
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .restrictions-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 420px; }
        .restrictions-table th {
            padding: 9px 10px; text-align: center;
            font-size: 11px; font-weight: 700; color: var(--ink-muted);
            text-transform: uppercase; letter-spacing: 0.6px;
            background: var(--surface-2); border-bottom: 1px solid var(--surface-3);
        }
        .restrictions-table th.jour-header {
            background: linear-gradient(135deg, var(--primary), var(--violet));
            color: white; font-size: 12px; text-transform: none; letter-spacing: 0; font-weight: 700;
        }
        .restrictions-table th.tache-nom { text-align: left; padding-left: 16px; }
        .restrictions-table td {
            padding: 10px; text-align: center;
            border-bottom: 1px solid var(--surface-3);
        }
        .restrictions-table td:first-child {
            text-align: left; padding-left: 16px;
            font-weight: 600; color: var(--ink);
        }
        .restrictions-table tr:last-child td { border-bottom: none; }
        .restrictions-table tr:hover { background: var(--surface-2); }
        .restrictions-table input[type="checkbox"] {
            width: 17px; height: 17px;
            accent-color: var(--primary); cursor: pointer;
        }

        .tache-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 2px 9px; border-radius: 20px;
            font-size: 12px; font-weight: 600;
        }
        .chip-entree     { background:#eff6ff; color:#2563eb; }
        .chip-mektaba    { background:#ecfdf5; color:#059669; }
        .chip-salle      { background:#fffbeb; color:#d97706; }
        .chip-amana_food { background:#fff1f2; color:#e11d48; }

        /* ── Mobile card layout for restrictions ── */
        .restrictions-mobile { display: none; }
        .restriction-card {
            border: 1px solid var(--surface-3);
            border-radius: var(--radius);
            margin-bottom: 12px; overflow: hidden;
        }
        .restriction-card-header {
            padding: 10px 14px;
            background: var(--surface-2);
            font-weight: 700; font-size: 13px; color: var(--ink);
        }
        .restriction-card-body { padding: 10px 14px; display: flex; flex-direction: column; gap: 10px; }
        .restriction-day-row {
            display: flex; align-items: center; justify-content: space-between;
            font-size: 13px; color: var(--ink-light);
        }
        .restriction-day-row label {
            display: flex; align-items: center; gap: 8px;
            font-weight: 400; font-size: 13px; cursor: pointer;
        }
        .restriction-day-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--primary); cursor: pointer; flex-shrink: 0;
        }

        /* ── Submit zone ── */
        .submit-zone {
            display: flex; align-items: center; gap: 16px;
            margin-top: 8px; flex-wrap: wrap;
        }
        .btn-submit {
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white; border: none; border-radius: var(--radius);
            font-size: 14.5px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(79,70,229,0.35);
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,0.45); }
        .btn-submit:active { transform: none; }
        .submit-note { font-size: 12.5px; color: var(--ink-muted); line-height: 1.5; }

        /* ════════════════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════════════════ */

        /* Tablet */
        @media (max-width: 720px) {
            .main { padding: 28px 20px 48px; }
            .page-title { font-size: 24px; }
            .card-body { padding: 18px; }
        }

        /* Mobile */
        @media (max-width: 600px) {
            .header { padding: 0 16px; }
            .header-logo-name { display: none; }
            .main { padding: 20px 16px 48px; }
            .page-title { font-size: 22px; }
            .page-sub { font-size: 13.5px; margin-bottom: 24px; }

            /* Single column forms */
            .form-grid { grid-template-columns: 1fr; gap: 14px; }
            .form-group.full { grid-column: 1; }

            /* Card */
            .card-body { padding: 16px; }
            .card-header { padding: 14px 16px; font-size: 14px; }

            /* Restrictions: hide table, show card layout */
            .restrictions-table-wrap { display: none; }
            .restrictions-mobile { display: block; }

            /* Submit */
            .submit-zone { flex-direction: column; align-items: stretch; }
            .btn-submit { width: 100%; text-align: center; justify-content: center; }
            .submit-note { text-align: center; }
        }

        @media (max-width: 380px) {
            .main { padding: 16px 12px 40px; }
        }
    </style>
</head>
<body>

{{-- Header --}}
<header class="header">
    <a href="{{ route('login') }}" class="header-logo">
        <div class="header-logo-icon">📅</div>
        <span class="header-logo-name">AMANA Planning</span>
    </a>
    <a href="{{ route('login') }}" class="header-login">← Connexion</a>
</header>

{{-- Main --}}
<div class="main">
    <div class="page-title">Rejoindre AMANA Planning</div>
    <div class="page-sub">
        Remplissez ce formulaire pour soumettre votre candidature.<br>
        Un administrateur la validera et vous recevrez un email pour créer votre mot de passe.
    </div>

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
                        <input type="text" id="prenom" name="prenom"
                               value="{{ old('prenom') }}" required maxlength="100"
                               placeholder="Votre prénom">
                        @error('prenom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom <span class="req">*</span></label>
                        <input type="text" id="nom" name="nom"
                               value="{{ old('nom') }}" required maxlength="100"
                               placeholder="Votre nom de famille">
                        @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="email">Adresse email <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email') }}" required maxlength="255"
                               placeholder="votre@email.fr">
                        <span class="form-hint">Ce sera votre identifiant de connexion</span>
                        @error('email')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                               value="{{ old('telephone') }}" maxlength="20"
                               placeholder="+33 6 00 00 00 00">
                        @error('telephone')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Informations bénévole --}}
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:#eff6ff;">🚗</div>
                Informations bénévole
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="date_inscription_benevole">Date d'inscription bénévole</label>
                        <input type="date" id="date_inscription_benevole"
                               name="date_inscription_benevole"
                               value="{{ old('date_inscription_benevole', now()->toDateString()) }}">
                        @error('date_inscription_benevole')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="id_vehicule">Véhicule disponible</label>
                        <select id="id_vehicule" name="id_vehicule">
                            <option value="">— Aucun véhicule —</option>
                            @foreach($vehicules as $v)
                                <option value="{{ $v->id }}"
                                    {{ old('id_vehicule') == $v->id ? 'selected' : '' }}>
                                    {{ $v->type }} ({{ $v->capacite_kg }} kg)
                                </option>
                            @endforeach
                        </select>
                        @error('id_vehicule')<span class="form-error">{{ $message }}</span>@enderror
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
                <div class="restrictions-info">
                    <span style="font-size:18px; flex-shrink:0;">ℹ️</span>
                    <span>
                        Cochez les cases pour les tâches que vous <strong>pouvez effectuer</strong> chaque jour.
                        Vous pourrez modifier ces disponibilités à tout moment depuis votre espace.
                    </span>
                </div>

                {{-- Desktop/tablet: table view --}}
                <div class="restrictions-table-wrap table-scroll">
                    <table class="restrictions-table">
                        <thead>
                            <tr>
                                <th class="tache-nom" rowspan="2" style="vertical-align:middle;">Tâche</th>
                                @foreach($jours as $jour)
                                    <th class="jour-header">{{ $jour }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($taches as $tache)
                                <tr>
                                    <td>
                                        <span class="tache-chip chip-{{ $tache->code }}">
                                            {{ $tache->libelle }}
                                        </span>
                                    </td>
                                    @foreach($jours as $jour)
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                value="1"
                                                title="{{ $tache->libelle }} — {{ $jour }}"
                                                {{ old('restrictions.' . $tache->id . '.' . $jour, '1') ? 'checked' : '' }}
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: card view --}}
                <div class="restrictions-mobile">
                    @foreach($taches as $tache)
                        <div class="restriction-card">
                            <div class="restriction-card-header">
                                <span class="tache-chip chip-{{ $tache->code }}">{{ $tache->libelle }}</span>
                            </div>
                            <div class="restriction-card-body">
                                @foreach($jours as $jour)
                                    <div class="restriction-day-row">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                value="1"
                                                {{ old('restrictions.' . $tache->id . '.' . $jour, '1') ? 'checked' : '' }}
                                            >
                                            {{ $jour }}
                                        </label>
                                        <span style="font-size:12px; color:var(--ink-muted);">Disponible</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>

        {{-- Submit --}}
        <div class="submit-zone">
            <button type="submit" class="btn-submit">
                ✉️ Soumettre ma candidature
            </button>
            <div class="submit-note">
                En soumettant ce formulaire, vous acceptez que vos informations<br>
                soient utilisées dans le cadre du bénévolat AMANA.
            </div>
        </div>

    </form>
</div>

</body>
</html>