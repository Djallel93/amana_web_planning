{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>
    <style>
        /* ── Variables ── */
        :root {
            --primary:       #667eea;
            --primary-dark:  #764ba2;
            --success:       #48bb78;
            --warning:       #ed8936;
            --danger:        #f56565;
            --info:          #4299e1;
            --bg-light:      #f7fafc;
            --text:          #2d3748;
            --text-muted:    #718096;
            --border:        #e2e8f0;
            --shadow:        0 4px 6px rgba(0,0,0,0.07);
            --radius:        10px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-light);
            color: var(--text);
            font-size: 14px;
            line-height: 1.6;
        }

        /* ── Navbar ── */
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            box-shadow: 0 2px 10px rgba(102,126,234,0.4);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            color: white;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: 0.5px;
        }
        .navbar-brand span { opacity: 0.8; font-weight: 400; }

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }
        .nav-link {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .nav-link.active { background: rgba(255,255,255,0.25); }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .navbar-user {
            color: rgba(255,255,255,0.85);
            font-size: 13px;
        }
        .btn-logout {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-family: inherit;
            transition: all 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.25); }

        /* ── Contenu principal ── */
        .main-content {
            padding: 28px 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ── Messages flash ── */
        .flash {
            padding: 12px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid;
        }
        .flash-success { background: #f0fff4; border-color: var(--success); color: #276749; }
        .flash-error   { background: #fff5f5; border-color: var(--danger);  color: #822727; }
        .flash-warning { background: #fffaf0; border-color: var(--warning); color: #7b341e; }
        .flash-info    { background: #ebf8ff; border-color: var(--info);    color: #2c5282; }

        /* ── Cards ── */
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border);
        }
        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        /* ── Boutons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn:active { transform: none; }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        .btn-success { background: var(--success); color: white; }
        .btn-danger  { background: var(--danger);  color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-secondary {
            background: var(--border);
            color: var(--text-muted);
        }
        .btn-sm { padding: 5px 12px; font-size: 12px; }

        /* ── Tableaux ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead tr { background: var(--bg-light); }
        th {
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            border-bottom: 2px solid var(--border);
        }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:hover { background: #fafbff; }
        tbody tr:last-child td { border-bottom: none; }

        /* ── Formulaires ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; }
        label { font-size: 13px; font-weight: 600; color: var(--text); }
        label .required { color: var(--danger); margin-left: 2px; }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        input[type="tel"],
        select,
        textarea {
            padding: 9px 13px;
            border: 2px solid var(--border);
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            background: white;
            transition: border-color 0.2s;
            width: 100%;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        textarea { resize: vertical; min-height: 80px; }
        .form-error { color: var(--danger); font-size: 12px; margin-top: 2px; }
        .form-hint  { color: var(--text-muted); font-size: 12px; margin-top: 2px; }

        /* Checkbox stylisée */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        .checkbox-group label { font-weight: 500; cursor: pointer; margin: 0; }

        /* ── Badges statut ── */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-success  { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
        .badge-warning  { background: #fffaf0; color: #7b341e; border: 1px solid #fbd38d; }
        .badge-danger   { background: #fff5f5; color: #822727; border: 1px solid #fed7d7; }
        .badge-info     { background: #ebf8ff; color: #2c5282; border: 1px solid #bee3f8; }
        .badge-muted    { background: #f7fafc; color: var(--text-muted); border: 1px solid var(--border); }

        /* ── Actions dans tableaux ── */
        .actions { display: flex; gap: 6px; }

        /* ── Formulaire de suppression inline ── */
        .form-delete { display: inline; }

        /* ── Page header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }
        .page-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Tâches (couleurs) ── */
        .tache-entree     { color: #3182ce; font-weight: 600; }
        .tache-mektaba    { color: #276749; font-weight: 600; }
        .tache-salle      { color: #c05621; font-weight: 600; }
        .tache-amana_food { color: #c53030; font-weight: 600; }
        .tache-vide       { color: var(--text-muted); font-style: italic; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .main-content { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .navbar-nav { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- ── Navigation ── --}}
<nav class="navbar">
    <a href="{{ route('planning.index') }}" class="navbar-brand">
        📅 AMANA <span>Planning</span>
    </a>

    <ul class="navbar-nav">
        <li>
            <a href="{{ route('planning.index') }}"
               class="nav-link {{ request()->routeIs('planning.*') ? 'active' : '' }}">
                📅 Planning
            </a>
        </li>
        <li>
            <a href="{{ route('personnes.index') }}"
               class="nav-link {{ request()->routeIs('personnes.*') ? 'active' : '' }}">
                👥 Personnes
            </a>
        </li>
        <li>
            <a href="{{ route('restrictions.index') }}"
               class="nav-link {{ request()->routeIs('restrictions.*') ? 'active' : '' }}">
                🔒 Restrictions
            </a>
        </li>
        <li>
            <a href="{{ route('absences.index') }}"
               class="nav-link {{ request()->routeIs('absences.*') ? 'active' : '' }}">
                🏖️ Absences
            </a>
        </li>
        <li>
            <a href="{{ route('evenements.index') }}"
               class="nav-link {{ request()->routeIs('evenements.*') ? 'active' : '' }}">
                🎉 Événements
            </a>
        </li>
        <li>
            <a href="{{ route('planning.statistics') }}"
               class="nav-link {{ request()->routeIs('planning.statistics') ? 'active' : '' }}">
                📊 Statistiques
            </a>
        </li>
    </ul>

    <div class="navbar-right">
        <span class="navbar-user">⚙️ {{ auth()->user()->name ?? 'Admin' }}</span>
        <form action="{{ route('logout') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn-logout">Déconnexion</button>
        </form>
    </div>
</nav>

{{-- ── Contenu ── --}}
<main class="main-content">

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="flash flash-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash flash-error">❌ {{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="flash flash-warning">⚠️ {{ session('warning') }}</div>
    @endif
    @if(session('info'))
        <div class="flash flash-info">ℹ️ {{ session('info') }}</div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
