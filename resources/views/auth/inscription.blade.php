{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --app-accent: #0369a1;
            --app-accent-dark: #0284c7;
            --app-accent-glow: rgba(3, 105, 161, 0.18);
            --app-sidebar-bg: #0c1e2e;
            --ink: #0d1117;
            --ink-light: #374151;
            --ink-muted: #6b7280;
            --ink-faint: #d1d5db;
            --surface: #ffffff;
            --surface-2: #f8f9fb;
            --surface-3: #f0f2f5;
            --surface-border: #e5e7eb;
            --rose: #e11d48;
            --radius: 10px;
            --radius-lg: 14px;
            --font-body: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-heading: 'Sora', system-ui, -apple-system, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: var(--font-body);
            background: var(--surface-2);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            padding: 0;
        }

        img,
        svg {
            display: block;
            max-width: 100%;
        }

        button {
            cursor: pointer;
            font-family: inherit;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        h1,
        h2,
        h3,
        h4 {
            font-family: var(--font-heading);
            margin: 0;
        }

        p {
            margin: 0;
        }

        /* ── Topbar ── */
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .topbar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .topbar-logo-img {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .topbar-logo-name {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 600;
            color: white;
        }

        .topbar-link {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.55);
            padding: 6px 14px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: var(--radius);
            transition: all 0.18s;
            white-space: nowrap;
        }

        .topbar-link:hover {
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
        }

        /* ── Main ── */
        .main {
            max-width: 760px;
            margin: 0 auto;
            padding: 40px 24px 64px;
        }

        .page-title {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
            letter-spacing: -0.2px;
        }

        .page-sub {
            font-size: 13.5px;
            color: var(--ink-muted);
            margin-bottom: 32px;
            line-height: 1.65;
        }

        /* ── Flash ── */
        .flash {
            padding: 12px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid;
            display: flex;
            align-items: flex-start;
            gap: 9px;
        }

        .flash-error {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #9f1239;
        }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 10px rgba(13, 17, 23, 0.07), 0 1px 3px rgba(13, 17, 23, 0.04);
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
            flex-shrink: 0;
        }

        .card-body {
            padding: 22px;
        }

        /* ── Form ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: 0.2px;
            display: block;
        }

        label .req {
            color: var(--rose);
            margin-left: 2px;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="tel"],
        select {
            padding: 9px 13px;
            border: 1.5px solid var(--ink-faint);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: var(--font-body);
            color: var(--ink);
            background: var(--surface);
            transition: all 0.18s;
            width: 100%;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
        }

        input[type="date"] {
            -webkit-appearance: auto;
            appearance: auto;
        }

        input:focus,
        select:focus {
            border-color: var(--app-accent);
            box-shadow: 0 0 0 3px var(--app-accent-glow);
            font-size: 16px;
        }

        input[type="date"]:focus {
            font-size: 13.5px;
        }

        .form-hint {
            font-size: 12px;
            color: var(--ink-muted);
        }

        .form-error {
            color: var(--rose);
            font-size: 12px;
        }

        /* ── Info notice ── */
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

        /* ── Restrictions table ── */
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .restrictions-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 380px;
        }

        .restrictions-table th {
            padding: 9px 10px;
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--surface-3);
            font-family: var(--font-body);
        }

        .restrictions-table th.jour-header {
            background: var(--app-accent);
            color: white;
            font-size: 12px;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 600;
        }

        .restrictions-table th.tache-col {
            text-align: left;
            padding-left: 16px;
        }

        .restrictions-table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid var(--surface-3);
        }

        .restrictions-table td:first-child {
            text-align: left;
            padding-left: 16px;
            font-weight: 600;
            color: var(--ink);
        }

        .restrictions-table tr:last-child td {
            border-bottom: none;
        }

        .restrictions-table tr:hover {
            background: var(--surface-2);
        }

        .restrictions-table input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--app-accent);
            cursor: pointer;
            -webkit-appearance: auto;
            appearance: auto;
        }

        /* Tache chips */
        .tache-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .chip-entree {
            background: #eff6ff;
            color: #2563eb;
        }

        .chip-mektaba {
            background: #ecfdf5;
            color: #059669;
        }

        .chip-salle {
            background: #fffbeb;
            color: #d97706;
        }

        .chip-amana_food {
            background: #fff1f2;
            color: #e11d48;
        }

        /* ── Mobile card restrictions ── */
        .restrictions-mobile {
            display: none;
        }

        .restriction-card {
            border: 1px solid var(--surface-border);
            border-radius: var(--radius);
            margin-bottom: 10px;
            overflow: hidden;
        }

        .restriction-card-header {
            padding: 10px 14px;
            background: var(--surface-2);
            font-weight: 700;
            font-size: 13px;
            color: var(--ink);
        }

        .restriction-card-body {
            padding: 10px 14px;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .restriction-day-row {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            color: var(--ink-light);
        }

        .restriction-day-row input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--app-accent);
            cursor: pointer;
            flex-shrink: 0;
            -webkit-appearance: auto;
            appearance: auto;
        }

        /* ── Submit ── */
        .submit-zone {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .btn-submit {
            padding: 11px 28px;
            background: var(--app-accent);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-body);
            transition: all 0.2s;
            box-shadow: 0 3px 14px rgba(3, 105, 161, 0.35);
            -webkit-tap-highlight-color: transparent;
            letter-spacing: 0.1px;
        }

        .btn-submit:hover {
            background: var(--app-accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 5px 20px rgba(3, 105, 161, 0.45);
        }

        .btn-submit:active {
            transform: none;
        }

        .submit-note {
            font-size: 12px;
            color: var(--ink-muted);
            line-height: 1.55;
        }

        /* ══════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════ */
        @media (max-width: 680px) {
            .topbar {
                padding: 0 16px;
            }

            .topbar-logo-name {
                display: none;
            }

            .main {
                padding: 20px 16px 48px;
            }

            .page-title {
                font-size: 22px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .form-group.full {
                grid-column: 1;
            }

            .card-body {
                padding: 16px;
            }

            .card-header {
                padding: 13px 16px;
            }

            .restrictions-table-wrap {
                display: none;
            }

            .restrictions-mobile {
                display: block;
            }

            .submit-zone {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-submit {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 400px) {
            .main {
                padding: 16px 12px 40px;
            }
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
                            <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" required
                                maxlength="100" placeholder="Votre prénom">
                            @error('prenom')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nom">Nom <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="100"
                                placeholder="Votre nom de famille">
                            @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="email">Adresse email <span class="req">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                maxlength="255" placeholder="votre@email.fr">
                            <span class="form-hint">Ce sera votre identifiant de connexion</span>
                            @error('email')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}"
                                maxlength="20" placeholder="+33 6 00 00 00 00">
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
                            <input type="date" id="date_inscription_benevole" name="date_inscription_benevole"
                                value="{{ old('date_inscription_benevole', now()->toDateString()) }}">
                            @error('date_inscription_benevole')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="id_vehicule">Véhicule disponible</label>
                            <select id="id_vehicule" name="id_vehicule">
                                <option value="">— Aucun véhicule —</option>
                                @foreach($vehicules as $v)
                                    <option value="{{ $v->id }}" {{ old('id_vehicule') == $v->id ? 'selected' : '' }}>
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
                    <div class="notice">
                        <span style="font-size:16px; flex-shrink:0;">ℹ️</span>
                        <span>Cochez les tâches que vous <strong>pouvez effectuer</strong> chaque jour. Vous pourrez
                            modifier ces disponibilités à tout moment depuis votre espace.</span>
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
                                                <input type="checkbox" name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                    value="1" title="{{ $tache->libelle }} — {{ $jour }}" {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}>
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
                                            <input type="checkbox" id="mob_{{ $tache->id }}_{{ $jour }}"
                                                name="restrictions[{{ $tache->id }}][{{ $jour }}]" value="1" {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}>
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