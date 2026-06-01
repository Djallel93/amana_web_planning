{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>
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
            --emerald-bg:  #ecfdf5;
            --amber:       #d97706;
            --amber-bg:    #fffbeb;
            --radius:      10px;
            --radius-lg:   16px;
            --shadow:      0 4px 12px rgba(15,17,23,0.08);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: var(--surface-2);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .header {
            background: var(--ink);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none;
        }
        .header-logo-icon {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--primary), var(--violet));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }
        .header-logo-name {
            font-family: 'DM Serif Display', serif;
            font-size: 16px; color: white;
        }
        .header-login {
            font-size: 13px; color: rgba(255,255,255,0.6);
            text-decoration: none;
            padding: 7px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: var(--radius);
            transition: all 0.18s;
        }
        .header-login:hover { color: white; border-color: rgba(255,255,255,0.5); }

        /* ── Main ── */
        .main {
            max-width: 780px;
            margin: 40px auto;
            padding: 0 24px 60px;
        }
        .page-title {
            font-family: 'DM Serif Display', serif;
            font-size: 28px; color: var(--ink);
            margin-bottom: 6px;
        }
        .page-sub {
            font-size: 14px; color: var(--ink-muted);
            margin-bottom: 32px; line-height: 1.6;
        }

        /* ── Cards ── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-header {
            display: flex; align-items: center; gap: 10px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--surface-3);
            font-size: 15px; font-weight: 700; color: var(--ink);
        }
        .card-header-icon {
            width: 30px; height: 30px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }
        .card-body { padding: 24px; }

        /* ── Form ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        label {
            font-size: 12.5px; font-weight: 700;
            color: var(--ink); letter-spacing: 0.2px;
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
        }
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.12);
        }
        .form-hint  { font-size: 12px; color: var(--ink-muted); }
        .form-error { color: var(--rose); font-size: 12px; }

        /* ── Flash ── */
        .flash {
            padding: 13px 18px; border-radius: var(--radius);
            margin-bottom: 22px; font-size: 13.5px; font-weight: 500;
            border: 1px solid; display: flex; align-items: center; gap: 10px;
        }
        .flash-error { background:#fff1f2; border-color:#fecdd3; color:#9f1239; }

        /* ── Restrictions grid ── */
        .restrictions-info {
            background: var(--amber-bg);
            border: 1px solid #fde68a;
            border-radius: var(--radius);
            padding: 12px 16px;
            font-size: 13px; color: #92400e;
            margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .restrictions-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .restrictions-table th {
            padding: 9px 10px; text-align: center;
            font-size: 11px; font-weight: 700;
            color: var(--ink-muted); text-transform: uppercase;
            letter-spacing: 0.6px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--surface-3);
        }
        .restrictions-table th.jour-header {
            background: linear-gradient(135deg, var(--primary), var(--violet));
            color: white; font-size: 12px; text-transform: none; letter-spacing: 0;
            font-weight: 700;
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

        /* ── Submit ── */
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
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,0.45); }
        .submit-note { font-size: 12.5px; color: var(--ink-muted); line-height: 1.5; }

        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .main { padding: 0 16px 40px; }
        }
    </style>
</head>
<body>

{{-- Header --}}
<div class="header">
    <a href="{{ route('login') }}" class="header-logo">
        <div class="header-logo-icon">📅</div>
        <span class="header-logo-name">AMANA Planning</span>
    </a>
    <a href="{{ route('login') }}" class="header-login">← Retour à la connexion</a>
</div>

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
                <div class="card-header-icon" style="background:var(--violet-bg, #f5f3ff);">👤</div>
                Informations personnelles
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="prenom">Prénom <span class="req">*</span></label>
                        <input type="text" id="prenom" name="prenom"
                               value="{{ old('prenom') }}"
                               required maxlength="100" placeholder="Votre prénom">
                        @error('prenom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom <span class="req">*</span></label>
                        <input type="text" id="nom" name="nom"
                               value="{{ old('nom') }}"
                               required maxlength="100" placeholder="Votre nom de famille">
                        @error('nom')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="email">Adresse email <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               value="{{ old('email') }}"
                               required maxlength="255" placeholder="votre@email.fr">
                        <span class="form-hint">Ce sera votre identifiant de connexion</span>
                        @error('email')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone"
                               value="{{ old('telephone') }}"
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

                <div style="overflow-x:auto;">
                    <table class="restrictions-table">
                        <thead>
                            <tr>
                                <th class="tache-nom" rowspan="2" style="vertical-align:middle;">Tâche</th>
                                @foreach($jours as $jour)
                                    <th class="jour-header" colspan="1">{{ $jour }}</th>
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
                                                {{-- Coché par défaut = disponible --}}
                                                {{ old('restrictions.' . $tache->id . '.' . $jour, '1') ? 'checked' : '' }}
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Bouton de soumission --}}
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
