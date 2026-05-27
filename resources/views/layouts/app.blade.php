{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        /* ── Design Tokens ─────────────────────────────────────────── */
        :root {
            --ink:         #0f1117;
            --ink-light:   #3d4151;
            --ink-muted:   #7a7f94;
            --ink-faint:   #c4c8d8;

            --surface:     #ffffff;
            --surface-2:   #f4f5f9;
            --surface-3:   #eceef5;

            --primary:     #4f46e5;
            --primary-2:   #6366f1;
            --primary-glow:rgba(79,70,229,0.18);

            --emerald:     #059669;
            --emerald-bg:  #ecfdf5;
            --amber:       #d97706;
            --amber-bg:    #fffbeb;
            --rose:        #e11d48;
            --rose-bg:     #fff1f2;
            --sky:         #0284c7;
            --sky-bg:      #f0f9ff;
            --violet:      #7c3aed;
            --violet-bg:   #f5f3ff;

            --sidebar-w:   248px;
            --topbar-h:    0px;

            --radius-sm:   6px;
            --radius:      10px;
            --radius-lg:   16px;
            --radius-xl:   22px;

            --shadow-sm:   0 1px 3px rgba(15,17,23,0.08), 0 1px 2px rgba(15,17,23,0.04);
            --shadow:      0 4px 12px rgba(15,17,23,0.08), 0 2px 4px rgba(15,17,23,0.05);
            --shadow-lg:   0 12px 32px rgba(15,17,23,0.12), 0 4px 8px rgba(15,17,23,0.06);
            --shadow-glow: 0 0 0 3px var(--primary-glow);

            --transition:  all 0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ── Reset ─────────────────────────────────────────────────── */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 14px; scroll-behavior: smooth; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface-2);
            color: var(--ink);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Sidebar ───────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--ink);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 200;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(79,70,229,0.35) 0%, transparent 70%);
            pointer-events: none;
        }

        .sidebar-brand {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            position: relative;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 11px;
            text-decoration: none;
        }
        .sidebar-logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            box-shadow: 0 4px 12px rgba(79,70,229,0.4);
            flex-shrink: 0;
        }
        .sidebar-logo-text {
            display: flex; flex-direction: column;
        }
        .sidebar-logo-name {
            font-family: 'DM Serif Display', serif;
            font-size: 17px;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }
        .sidebar-logo-sub {
            font-size: 10.5px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            font-weight: 500;
        }

        .sidebar-section {
            padding: 20px 16px 8px;
            flex: 1;
            overflow-y: auto;
        }
        .sidebar-section::-webkit-scrollbar { width: 0; }

        .sidebar-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.28);
            padding: 0 10px;
            margin-bottom: 6px;
            margin-top: 12px;
        }
        .sidebar-label:first-child { margin-top: 0; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 450;
            transition: var(--transition);
            position: relative;
            margin-bottom: 2px;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.92);
        }
        .nav-item.active {
            background: linear-gradient(135deg, rgba(79,70,229,0.28) 0%, rgba(124,58,237,0.18) 100%);
            color: #ffffff;
        }
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 3px; height: 60%;
            background: var(--primary-2);
            border-radius: 0 3px 3px 0;
        }
        .nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }
        .nav-text { flex: 1; }
        .nav-badge {
            background: var(--primary);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            background: rgba(255,255,255,0.05);
        }
        .user-avatar {
            width: 30px; height: 30px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; color: white; font-weight: 700;
            flex-shrink: 0;
        }
        .user-name {
            flex: 1;
            font-size: 12.5px;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .btn-logout-sidebar {
            background: none; border: none;
            color: rgba(255,255,255,0.35);
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
            border-radius: 4px;
            transition: var(--transition);
            line-height: 1;
        }
        .btn-logout-sidebar:hover { color: var(--rose); }

        /* ── Main content ──────────────────────────────────────────── */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .main-content {
            padding: 32px 36px;
            flex: 1;
            max-width: 1440px;
            width: 100%;
        }

        /* ── Flash Messages ────────────────────────────────────────── */
        .flash {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 500;
            border: 1px solid;
            animation: slideIn 0.25s ease;
        }
        @keyframes slideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .flash-success { background: var(--emerald-bg); border-color: #a7f3d0; color: #065f46; }
        .flash-error   { background: var(--rose-bg);    border-color: #fecdd3; color: #9f1239; }
        .flash-warning { background: var(--amber-bg);   border-color: #fde68a; color: #92400e; }
        .flash-info    { background: var(--sky-bg);     border-color: #bae6fd; color: #0c4a6e; }
        .flash-icon { font-size: 16px; flex-shrink: 0; }
        .flash-close {
            margin-left: auto; background: none; border: none;
            cursor: pointer; opacity: 0.5; font-size: 16px;
            transition: var(--transition);
            color: inherit;
        }
        .flash-close:hover { opacity: 1; }

        /* ── Page Header ───────────────────────────────────────────── */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
        }
        .page-header-left {}
        .page-title {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--ink);
            line-height: 1.2;
            letter-spacing: -0.3px;
        }
        .page-subtitle {
            font-size: 13.5px;
            color: var(--ink-muted);
            margin-top: 4px;
            font-weight: 400;
        }
        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        /* ── Buttons ───────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: var(--transition);
            white-space: nowrap;
            letter-spacing: 0.1px;
            line-height: 1.4;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(79,70,229,0.35);
        }
        .btn-primary:hover { box-shadow: 0 6px 20px rgba(79,70,229,0.45); }

        .btn-success {
            background: linear-gradient(135deg, var(--emerald) 0%, #10b981 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(5,150,105,0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--rose) 0%, #f43f5e 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(225,29,72,0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--amber) 0%, #f59e0b 100%);
            color: white;
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--ink-light);
            border: 1.5px solid var(--surface-3);
            box-shadow: var(--shadow-sm);
        }
        .btn-secondary:hover { background: var(--surface-2); border-color: var(--ink-faint); }

        .btn-ghost {
            background: transparent;
            color: var(--ink-muted);
            border: 1.5px solid var(--ink-faint);
        }
        .btn-ghost:hover { background: var(--surface-3); color: var(--ink); }

        .btn-sm { padding: 6px 13px; font-size: 12px; border-radius: var(--radius-sm); }
        .btn-lg { padding: 12px 24px; font-size: 14.5px; }
        .btn-icon { padding: 8px; border-radius: var(--radius-sm); }
        .btn-icon.btn-sm { padding: 6px; }

        /* ── Cards ─────────────────────────────────────────────────── */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .card-body { padding: 24px; }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid var(--surface-3);
        }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .card-title-icon {
            width: 30px; height: 30px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
        }

        /* Stat cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.color-primary::after { background: linear-gradient(90deg, var(--primary), var(--violet)); }
        .stat-card.color-emerald::after { background: linear-gradient(90deg, var(--emerald), #10b981); }
        .stat-card.color-amber::after   { background: linear-gradient(90deg, var(--amber), #f59e0b); }
        .stat-card.color-sky::after     { background: linear-gradient(90deg, var(--sky), #38bdf8); }
        .stat-card.color-rose::after    { background: linear-gradient(90deg, var(--rose), #f43f5e); }
        .stat-card.color-violet::after  { background: linear-gradient(90deg, var(--violet), #a78bfa); }
        .stat-value {
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 6px;
            letter-spacing: -1px;
        }
        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .stat-sub { font-size: 11.5px; color: var(--ink-muted); margin-top: 4px; }

        /* ── Tables ────────────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        thead th {
            padding: 11px 16px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--surface-3);
            white-space: nowrap;
        }
        thead th:first-child { border-radius: var(--radius-sm) 0 0 0; }
        thead th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }
        tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--surface-3);
            vertical-align: middle;
            color: var(--ink-light);
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.1s; }
        tbody tr:hover { background: var(--surface-2); }
        .td-primary { font-weight: 600; color: var(--ink); }

        /* ── Badges ────────────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .badge-success  { background: var(--emerald-bg); color: #065f46; border: 1px solid #a7f3d0; }
        .badge-warning  { background: var(--amber-bg);   color: #92400e; border: 1px solid #fde68a; }
        .badge-danger   { background: var(--rose-bg);    color: #9f1239; border: 1px solid #fecdd3; }
        .badge-info     { background: var(--sky-bg);     color: #0c4a6e; border: 1px solid #bae6fd; }
        .badge-muted    { background: var(--surface-3);  color: var(--ink-muted); border: 1px solid var(--ink-faint); }
        .badge-primary  { background: var(--violet-bg);  color: #5b21b6; border: 1px solid #ddd6fe; }
        .badge-dot::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; background: currentColor;
        }

        /* ── Forms ─────────────────────────────────────────────────── */
        .form-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-group  { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group.span-2 { grid-column: span 2; }

        label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: 0.2px;
        }
        label .req { color: var(--rose); margin-left: 2px; }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        input[type="tel"],
        input[type="password"],
        select,
        textarea {
            padding: 9px 13px;
            border: 1.5px solid var(--ink-faint);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: inherit;
            color: var(--ink);
            background: var(--surface);
            transition: var(--transition);
            width: 100%;
            outline: none;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-glow);
        }
        input:hover:not(:focus), select:hover:not(:focus), textarea:hover:not(:focus) {
            border-color: var(--ink-muted);
        }
        textarea { resize: vertical; min-height: 80px; }
        .form-error { color: var(--rose); font-size: 12px; margin-top: 2px; }
        .form-hint  { color: var(--ink-muted); font-size: 12px; margin-top: 2px; }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }
        .checkbox-wrap input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-wrap label { font-weight: 500; cursor: pointer; font-size: 13.5px; }

        /* ── Divider ───────────────────────────────────────────────── */
        .divider {
            height: 1px;
            background: var(--surface-3);
            margin: 20px 0;
        }
        .divider-label {
            text-align: center;
            position: relative;
            margin: 20px 0;
            color: var(--ink-muted);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .divider-label::before, .divider-label::after {
            content: '';
            position: absolute;
            top: 50%; width: calc(50% - 60px);
            height: 1px; background: var(--surface-3);
        }
        .divider-label::before { left: 0; }
        .divider-label::after  { right: 0; }

        /* ── Task Colors ───────────────────────────────────────────── */
        .tache-entree     { color: #2563eb; font-weight: 600; }
        .tache-mektaba    { color: #059669; font-weight: 600; }
        .tache-salle      { color: #d97706; font-weight: 600; }
        .tache-amana_food { color: #e11d48; font-weight: 600; }
        .tache-vide       { color: var(--ink-faint); font-style: italic; font-size: 12px; }

        /* ── Actions ───────────────────────────────────────────────── */
        .actions { display: flex; gap: 6px; align-items: center; }
        .form-delete { display: inline; margin: 0; padding: 0; }

        /* ── Empty State ───────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 64px 32px;
        }
        .empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .empty-title { font-size: 17px; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
        .empty-desc { color: var(--ink-muted); font-size: 13.5px; margin-bottom: 24px; }

        /* ── Scrollbar ─────────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--ink-faint); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--ink-muted); }

        /* ── Responsive ────────────────────────────────────────────── */
        @media (max-width: 900px) {
            .sidebar { display: none; }
            .main-wrapper { margin-left: 0; }
            .main-content { padding: 20px 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .stat-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- ── Sidebar ── --}}
<aside class="sidebar">
    <div class="sidebar-brand">
        <a href="{{ route('planning.index') }}" class="sidebar-logo">
            <div class="sidebar-logo-icon">📅</div>
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-name">AMANA</span>
                <span class="sidebar-logo-sub">Planning</span>
            </div>
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-label">Principal</div>
        <a href="{{ route('planning.index') }}"
           class="nav-item {{ request()->routeIs('planning.index') ? 'active' : '' }}">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Planning</span>
        </a>
        <a href="{{ route('planning.generate.form') }}"
           class="nav-item {{ request()->routeIs('planning.generate*') ? 'active' : '' }}">
            <span class="nav-icon">✨</span>
            <span class="nav-text">Générer</span>
        </a>
        <a href="{{ route('planning.statistics') }}"
           class="nav-item {{ request()->routeIs('planning.statistics') ? 'active' : '' }}">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Statistiques</span>
        </a>

        <div class="sidebar-label">Gestion</div>
        <a href="{{ route('personnes.index') }}"
           class="nav-item {{ request()->routeIs('personnes.*') ? 'active' : '' }}">
            <span class="nav-icon">👥</span>
            <span class="nav-text">Personnes</span>
        </a>
        <a href="{{ route('restrictions.index') }}"
           class="nav-item {{ request()->routeIs('restrictions.*') ? 'active' : '' }}">
            <span class="nav-icon">🔒</span>
            <span class="nav-text">Restrictions</span>
        </a>
        <a href="{{ route('absences.index') }}"
           class="nav-item {{ request()->routeIs('absences.*') ? 'active' : '' }}">
            <span class="nav-icon">🏖️</span>
            <span class="nav-text">Absences</span>
        </a>
        <a href="{{ route('evenements.index') }}"
           class="nav-item {{ request()->routeIs('evenements.*') ? 'active' : '' }}">
            <span class="nav-icon">🎉</span>
            <span class="nav-text">Événements</span>
        </a>

        <div class="sidebar-label">Exports</div>
        <a href="{{ route('planning.export.form') }}"
           class="nav-item {{ request()->routeIs('planning.export*') ? 'active' : '' }}">
            <span class="nav-icon">📄</span>
            <span class="nav-text">Export PDF</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</div>
            <span class="user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn-logout-sidebar" title="Déconnexion">↪</button>
            </form>
        </div>
    </div>
</aside>

{{-- ── Main ── --}}
<div class="main-wrapper">
    <main class="main-content">
        @if(session('success'))
            <div class="flash flash-success">
                <span class="flash-icon">✅</span>
                <span>{{ session('success') }}</span>
                <button class="flash-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif
        @if(session('error'))
            <div class="flash flash-error">
                <span class="flash-icon">❌</span>
                <span>{{ session('error') }}</span>
                <button class="flash-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif
        @if(session('warning'))
            <div class="flash flash-warning">
                <span class="flash-icon">⚠️</span>
                <span>{{ session('warning') }}</span>
                <button class="flash-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif
        @if(session('info'))
            <div class="flash flash-info">
                <span class="flash-icon">ℹ️</span>
                <span>{{ session('info') }}</span>
                <button class="flash-close" onclick="this.parentElement.remove()">×</button>
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
