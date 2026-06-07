{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AMANA Planning')</title>

    {{-- ── Normalize.css ── --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    {{-- ── Fonts: Sora (headings) + Plus Jakarta Sans (body) ── --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap"
        rel="stylesheet">

    <style>
        /* ══════════════════════════════════════════════════════════════
           DESIGN TOKENS
        ══════════════════════════════════════════════════════════════ */
        :root {
            /* ── App identity: Ocean / Bleu Pétrole ── */
            --app-sidebar-bg: #0c1e2e;
            --app-sidebar-bg-2: #0f2740;
            --app-accent: #0369a1;
            --app-accent-light: #0ea5e9;
            --app-accent-glow: rgba(3, 105, 161, 0.25);
            --app-accent-subtle: rgba(14, 165, 233, 0.15);

            /* ── Neutrals ── */
            --ink: #0d1117;
            --ink-light: #374151;
            --ink-muted: #6b7280;
            --ink-faint: #d1d5db;

            /* ── Surfaces ── */
            --surface: #ffffff;
            --surface-2: #f8f9fb;
            --surface-3: #f0f2f5;
            --surface-border: #e5e7eb;

            /* ── Semantic colours ── */
            --emerald: #059669;
            --emerald-bg: #ecfdf5;
            --emerald-border: #a7f3d0;

            --amber: #d97706;
            --amber-bg: #fffbeb;
            --amber-border: #fde68a;

            --rose: #e11d48;
            --rose-bg: #fff1f2;
            --rose-border: #fecdd3;

            --sky: #0284c7;
            --sky-bg: #f0f9ff;
            --sky-border: #bae6fd;

            --violet: #7c3aed;
            --violet-bg: #f5f3ff;
            --violet-border: #ddd6fe;

            /* ── Layout ── */
            --sidebar-w: 252px;
            --topbar-h: 56px;

            /* ── Radii ── */
            --radius-sm: 6px;
            --radius: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;

            /* ── Shadows ── */
            --shadow-sm: 0 1px 3px rgba(13, 17, 23, 0.07), 0 1px 2px rgba(13, 17, 23, 0.04);
            --shadow: 0 4px 12px rgba(13, 17, 23, 0.08), 0 2px 4px rgba(13, 17, 23, 0.05);
            --shadow-lg: 0 12px 32px rgba(13, 17, 23, 0.12), 0 4px 8px rgba(13, 17, 23, 0.06);
            --shadow-glow: 0 0 0 3px var(--app-accent-glow);

            /* ── Motion ── */
            --transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);

            /* ── Typography ── */
            --font-body: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-heading: 'Sora', system-ui, -apple-system, sans-serif;
        }

        /* ══════════════════════════════════════════════════════════════
           BASE RESET (post-normalize)
        ══════════════════════════════════════════════════════════════ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            font-size: 14px;
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: var(--font-body);
            background: var(--surface-2);
            color: var(--ink);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
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
        h4,
        h5,
        h6 {
            font-family: var(--font-heading);
            line-height: 1.25;
            margin: 0;
        }

        p {
            margin: 0;
        }

        /* ══════════════════════════════════════════════════════════════
           MOBILE TOPBAR
        ══════════════════════════════════════════════════════════════ */
        .mobile-topbar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--topbar-h);
            background: var(--app-sidebar-bg);
            z-index: 300;
            align-items: center;
            justify-content: space-between;
            padding: 0 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .mobile-topbar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .mobile-topbar-logo-img {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .mobile-topbar-name {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 600;
            color: white;
        }

        .hamburger {
            background: none;
            border: none;
            padding: 6px;
            color: rgba(255, 255, 255, 0.7);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .hamburger:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
        }

        .hamburger span {
            display: block;
            width: 18px;
            height: 2px;
            background: currentColor;
            border-radius: 2px;
            transition: var(--transition);
            transform-origin: center;
        }

        .hamburger.open span:nth-child(1) {
            transform: translateY(7px) rotate(45deg);
        }

        .hamburger.open span:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }

        .hamburger.open span:nth-child(3) {
            transform: translateY(-7px) rotate(-45deg);
        }

        /* ══════════════════════════════════════════════════════════════
           SIDEBAR OVERLAY (mobile)
        ══════════════════════════════════════════════════════════════ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 198;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity 0.25s;
        }

        .sidebar-overlay.visible {
            opacity: 1;
        }

        /* ══════════════════════════════════════════════════════════════
           SIDEBAR
        ══════════════════════════════════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--app-sidebar-bg);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 200;
            overflow: hidden;
            transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* subtle top glow */
        .sidebar::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(3, 105, 161, 0.3) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ── Brand ── */
        .sidebar-brand {
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            position: relative;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 11px;
            text-decoration: none;
        }

        .sidebar-logo-img {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .sidebar-logo-text {
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo-name {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 600;
            color: #ffffff;
            line-height: 1.1;
            letter-spacing: 0.2px;
        }

        .sidebar-logo-sub {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.35);
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 500;
            margin-top: 2px;
        }

        /* ── Nav ── */
        .sidebar-section {
            padding: 16px 14px 10px;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
        }

        .sidebar-section::-webkit-scrollbar {
            width: 0;
        }

        .sidebar-label {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.22);
            padding: 0 10px;
            margin-bottom: 5px;
            margin-top: 14px;
        }

        .sidebar-label:first-child {
            margin-top: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            color: rgba(255, 255, 255, 0.52);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            margin-bottom: 1px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.88);
        }

        .nav-item.active {
            background: var(--app-accent-subtle);
            color: #ffffff;
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 56%;
            background: var(--app-accent-light);
            border-radius: 0 3px 3px 0;
        }

        .nav-icon {
            font-size: 14px;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-text {
            flex: 1;
        }

        .nav-badge {
            background: var(--rose);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.65
            }
        }

        /* ── Role badge ── */
        .role-badge {
            margin: 0 14px 10px;
            padding: 7px 11px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .role-badge.admin {
            background: rgba(225, 29, 72, 0.14);
            color: #fda4af;
            border: 1px solid rgba(225, 29, 72, 0.22);
        }

        .role-badge.gestionnaire {
            background: rgba(217, 119, 6, 0.14);
            color: #fcd34d;
            border: 1px solid rgba(217, 119, 6, 0.22);
        }

        .role-badge.membre {
            background: rgba(14, 165, 233, 0.14);
            color: #7dd3fc;
            border: 1px solid rgba(14, 165, 233, 0.22);
        }

        /* ── Footer ── */
        .sidebar-footer {
            padding: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.05);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--app-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            overflow: hidden;
            min-width: 0;
        }

        .user-name {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.78);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-role {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.32);
            margin-top: 1px;
        }

        .btn-logout-sidebar {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            border-radius: 4px;
            transition: var(--transition);
            line-height: 1;
            flex-shrink: 0;
        }

        .btn-logout-sidebar:hover {
            color: var(--rose);
        }

        /* ══════════════════════════════════════════════════════════════
           MAIN CONTENT
        ══════════════════════════════════════════════════════════════ */
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

        /* ══════════════════════════════════════════════════════════════
           FLASH MESSAGES
        ══════════════════════════════════════════════════════════════ */
        .flash {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 13px 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 500;
            border: 1px solid;
            animation: slideIn 0.22s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .flash-success {
            background: var(--emerald-bg);
            border-color: var(--emerald-border);
            color: #065f46;
        }

        .flash-error {
            background: var(--rose-bg);
            border-color: var(--rose-border);
            color: #9f1239;
        }

        .flash-warning {
            background: var(--amber-bg);
            border-color: var(--amber-border);
            color: #92400e;
        }

        .flash-info {
            background: var(--sky-bg);
            border-color: var(--sky-border);
            color: #0c4a6e;
        }

        .flash-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.45;
            font-size: 16px;
            transition: var(--transition);
            color: inherit;
            flex-shrink: 0;
            line-height: 1;
            padding: 0;
        }

        .flash-close:hover {
            opacity: 1;
        }

        /* ══════════════════════════════════════════════════════════════
           PAGE HEADER
        ══════════════════════════════════════════════════════════════ */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .page-title {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.2;
            letter-spacing: -0.3px;
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--ink-muted);
        }

        .page-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        /* ══════════════════════════════════════════════════════════════
           BUTTONS
        ══════════════════════════════════════════════════════════════ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font-body);
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: var(--transition);
            white-space: nowrap;
            letter-spacing: 0.1px;
            line-height: 1.4;
            -webkit-tap-highlight-color: transparent;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-primary {
            background: var(--app-accent);
            color: white;
            box-shadow: 0 3px 12px rgba(3, 105, 161, 0.35);
        }

        .btn-primary:hover {
            background: #0284c7;
            box-shadow: 0 5px 18px rgba(3, 105, 161, 0.45);
        }

        .btn-success {
            background: var(--emerald);
            color: white;
            box-shadow: 0 3px 12px rgba(5, 150, 105, 0.3);
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-danger {
            background: var(--rose);
            color: white;
            box-shadow: 0 3px 12px rgba(225, 29, 72, 0.3);
        }

        .btn-danger:hover {
            background: #be123c;
        }

        .btn-warning {
            background: var(--amber);
            color: white;
            box-shadow: 0 3px 12px rgba(217, 119, 6, 0.3);
        }

        .btn-warning:hover {
            background: #b45309;
        }

        .btn-secondary {
            background: var(--surface);
            color: var(--ink-light);
            border: 1.5px solid var(--surface-border);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: var(--surface-3);
            border-color: var(--ink-faint);
        }

        .btn-ghost {
            background: transparent;
            color: var(--ink-muted);
            border: 1.5px solid var(--ink-faint);
        }

        .btn-ghost:hover {
            background: var(--surface-3);
            color: var(--ink);
        }

        .btn-sm {
            padding: 6px 13px;
            font-size: 12px;
            border-radius: var(--radius-sm);
        }

        .btn-lg {
            padding: 11px 24px;
            font-size: 14px;
        }

        .btn-icon {
            padding: 7px;
            border-radius: var(--radius-sm);
        }

        /* ══════════════════════════════════════════════════════════════
           CARDS
        ══════════════════════════════════════════════════════════════ */
        .card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--surface-border);
            overflow: hidden;
        }

        .card-body {
            padding: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
            border-bottom: 1px solid var(--surface-3);
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-title {
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-title-icon {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        /* ══════════════════════════════════════════════════════════════
           STAT CARDS
        ══════════════════════════════════════════════════════════════ */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            border: 1px solid var(--surface-border);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-card.color-primary::after {
            background: var(--app-accent);
        }

        .stat-card.color-emerald::after {
            background: var(--emerald);
        }

        .stat-card.color-amber::after {
            background: var(--amber);
        }

        .stat-card.color-sky::after {
            background: var(--sky);
        }

        .stat-card.color-rose::after {
            background: var(--rose);
        }

        .stat-card.color-violet::after {
            background: var(--violet);
        }

        .stat-value {
            font-family: var(--font-heading);
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .stat-sub {
            font-size: 11.5px;
            color: var(--ink-muted);
            margin-top: 4px;
        }

        /* ══════════════════════════════════════════════════════════════
           TABLES
        ══════════════════════════════════════════════════════════════ */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        thead th {
            padding: 10px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: 0.7px;
            background: var(--surface-2);
            border-bottom: 1px solid var(--surface-3);
            white-space: nowrap;
            font-family: var(--font-body);
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--surface-3);
            vertical-align: middle;
            color: var(--ink-light);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: background 0.1s;
        }

        tbody tr:hover {
            background: var(--surface-2);
        }

        .td-primary {
            font-weight: 600;
            color: var(--ink);
        }

        /* ══════════════════════════════════════════════════════════════
           BADGES
        ══════════════════════════════════════════════════════════════ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
            letter-spacing: 0.1px;
            border: 1px solid;
        }

        .badge-success {
            background: var(--emerald-bg);
            color: #065f46;
            border-color: var(--emerald-border);
        }

        .badge-warning {
            background: var(--amber-bg);
            color: #92400e;
            border-color: var(--amber-border);
        }

        .badge-danger {
            background: var(--rose-bg);
            color: #9f1239;
            border-color: var(--rose-border);
        }

        .badge-info {
            background: var(--sky-bg);
            color: #0c4a6e;
            border-color: var(--sky-border);
        }

        .badge-muted {
            background: var(--surface-3);
            color: var(--ink-muted);
            border-color: var(--ink-faint);
        }

        .badge-primary {
            background: var(--sky-bg);
            color: var(--sky);
            border-color: var(--sky-border);
        }

        .badge-dot::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }

        /* ══════════════════════════════════════════════════════════════
           FORMS
        ══════════════════════════════════════════════════════════════ */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-grid-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group.span-2 {
            grid-column: span 2;
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
        input[type="number"],
        input[type="tel"],
        input[type="password"],
        select,
        textarea {
            padding: 9px 13px;
            border: 1.5px solid var(--ink-faint);
            border-radius: var(--radius);
            font-size: 13.5px;
            font-family: var(--font-body);
            color: var(--ink);
            background: var(--surface);
            transition: var(--transition);
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
        select:focus,
        textarea:focus {
            border-color: var(--app-accent);
            box-shadow: var(--shadow-glow);
        }

        input:hover:not(:focus),
        select:hover:not(:focus),
        textarea:hover:not(:focus) {
            border-color: var(--ink-muted);
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-error {
            color: var(--rose);
            font-size: 12px;
            margin-top: 2px;
        }

        .form-hint {
            color: var(--ink-muted);
            font-size: 12px;
            margin-top: 2px;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }

        .checkbox-wrap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--app-accent);
            cursor: pointer;
            flex-shrink: 0;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .checkbox-wrap label {
            font-weight: 500;
            cursor: pointer;
            font-size: 13.5px;
        }

        /* ══════════════════════════════════════════════════════════════
           MISC UTILITIES
        ══════════════════════════════════════════════════════════════ */
        .divider {
            height: 1px;
            background: var(--surface-3);
            margin: 20px 0;
        }

        /* Task colours */
        .tache-entree {
            color: #2563eb;
            font-weight: 600;
        }

        .tache-mektaba {
            color: #059669;
            font-weight: 600;
        }

        .tache-salle {
            color: #d97706;
            font-weight: 600;
        }

        .tache-amana_food {
            color: #e11d48;
            font-weight: 600;
        }

        .tache-vide {
            color: var(--ink-faint);
            font-style: italic;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-delete {
            display: inline;
            margin: 0;
            padding: 0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 64px 32px;
        }

        .empty-icon {
            font-size: 44px;
            margin-bottom: 14px;
            opacity: 0.4;
        }

        .empty-title {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .empty-desc {
            color: var(--ink-muted);
            font-size: 13.5px;
            margin-bottom: 22px;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--ink-faint);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--ink-muted);
        }

        /* ══════════════════════════════════════════════════════════════
           RESPONSIVE — Tablet ≤ 1024px
        ══════════════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .main-content {
                padding: 28px;
            }

            .stat-grid {
                grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            }
        }

        /* ══════════════════════════════════════════════════════════════
           RESPONSIVE — Mobile ≤ 768px
        ══════════════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .mobile-topbar {
                display: flex;
            }

            .sidebar-overlay {
                display: block;
            }

            .sidebar {
                top: 0;
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: var(--shadow-lg);
            }

            .main-wrapper {
                margin-left: 0;
                padding-top: var(--topbar-h);
            }

            .main-content {
                padding: 20px 16px 32px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 20px;
                gap: 12px;
            }

            .page-header-actions {
                width: 100%;
            }

            .page-title {
                font-size: 20px;
            }

            .stat-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .stat-value {
                font-size: 24px;
            }

            .form-grid,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }

            .form-group.span-2,
            .form-group.full {
                grid-column: 1;
            }

            .card-body {
                padding: 16px;
            }

            .card-header {
                padding: 14px 16px;
            }

            .table-wrap {
                margin: 0 -16px;
            }

            table {
                font-size: 12.5px;
            }

            thead th {
                padding: 9px 12px;
                font-size: 10px;
            }

            tbody td {
                padding: 10px 12px;
            }

            .btn-lg {
                width: 100%;
                justify-content: center;
            }

            .empty-state {
                padding: 40px 16px;
            }

            /* prevent iOS zoom on focus */
            input[type="text"]:focus,
            input[type="email"]:focus,
            input[type="date"]:focus,
            input[type="number"]:focus,
            input[type="tel"]:focus,
            input[type="password"]:focus,
            select:focus,
            textarea:focus {
                font-size: 16px;
            }
        }

        /* ══════════════════════════════════════════════════════════════
           RESPONSIVE — Small mobile ≤ 480px
        ══════════════════════════════════════════════════════════════ */
        @media (max-width: 480px) {
            :root {
                --topbar-h: 52px;
            }

            .stat-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 14px;
            }

            .stat-value {
                font-size: 22px;
            }

            .page-title {
                font-size: 19px;
            }

            .flash {
                padding: 10px 14px;
                font-size: 13px;
            }

            .badge {
                font-size: 10.5px;
                padding: 2px 7px;
            }
        }
    </style>
    @stack('styles')
</head>

<body>

    {{-- ── Mobile topbar ── --}}
    <div class="mobile-topbar" id="mobileTopbar">
        <a href="{{ route('planning.index') }}" class="mobile-topbar-logo">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="mobile-topbar-logo-img">
            <span class="mobile-topbar-name">AMANA</span>
        </a>
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>

    {{-- ── Sidebar overlay ── --}}
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    {{-- ── Sidebar ── --}}
    <aside class="sidebar" id="mainSidebar" aria-label="Navigation principale">

        <div class="sidebar-brand">
            <a href="{{ route('planning.index') }}" class="sidebar-logo">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="sidebar-logo-img">
                <div class="sidebar-logo-text">
                    <span class="sidebar-logo-name">AMANA</span>
                    <span class="sidebar-logo-sub">Planning</span>
                </div>
            </a>
        </div>

        <div class="sidebar-section">

            @auth
                @if(auth()->user()->isAdmin())
                    <div class="role-badge admin">🛡️ Administrateur</div>
                @elseif(auth()->user()->isGestionnaire())
                    <div class="role-badge gestionnaire">⚙️ Gestionnaire</div>
                @else
                    <div class="role-badge membre">👤 Membre</div>
                @endif
            @endauth

            <div class="sidebar-label">Planning</div>
            <a href="{{ route('planning.index') }}"
                class="nav-item {{ request()->routeIs('planning.index') ? 'active' : '' }}" onclick="closeSidebar()">
                <span class="nav-icon">📅</span>
                <span class="nav-text">Planning</span>
            </a>
            <a href="{{ route('planning.statistics') }}"
                class="nav-item {{ request()->routeIs('planning.statistics') ? 'active' : '' }}"
                onclick="closeSidebar()">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Statistiques</span>
            </a>
            <a href="{{ route('planning.export.form') }}"
                class="nav-item {{ request()->routeIs('planning.export*') ? 'active' : '' }}" onclick="closeSidebar()">
                <span class="nav-icon">📄</span>
                <span class="nav-text">Export PDF</span>
            </a>

            <div class="sidebar-label">Mes données</div>
            <a href="{{ route('absences.index') }}"
                class="nav-item {{ request()->routeIs('absences.*') ? 'active' : '' }}" onclick="closeSidebar()">
                <span class="nav-icon">🏖️</span>
                <span class="nav-text">Absences</span>
            </a>
            <a href="{{ route('restrictions.index') }}"
                class="nav-item {{ request()->routeIs('restrictions.*') ? 'active' : '' }}" onclick="closeSidebar()">
                <span class="nav-icon">🔒</span>
                <span class="nav-text">Disponibilités</span>
            </a>

            @auth
                @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                    <div class="sidebar-label">Gestion</div>
                    <a href="{{ route('planning.generate.form') }}"
                        class="nav-item {{ request()->routeIs('planning.generate*') ? 'active' : '' }}"
                        onclick="closeSidebar()">
                        <span class="nav-icon">✨</span>
                        <span class="nav-text">Générer</span>
                    </a>
                    <a href="{{ route('evenements.index') }}"
                        class="nav-item {{ request()->routeIs('evenements.*') ? 'active' : '' }}" onclick="closeSidebar()">
                        <span class="nav-icon">🎉</span>
                        <span class="nav-text">Événements</span>
                    </a>
                @endif
            @endauth

            @auth
                @if(auth()->user()->isAdmin())
                    <div class="sidebar-label">Administration</div>
                    <a href="{{ route('personnes.index') }}"
                        class="nav-item {{ request()->routeIs('personnes.*') ? 'active' : '' }}" onclick="closeSidebar()">
                        <span class="nav-icon">👥</span>
                        <span class="nav-text">Personnes</span>
                    </a>
                    @php $nbCandidatures = \App\Models\Personne::enAttente()->count(); @endphp
                    <a href="{{ route('admin.candidatures.index') }}"
                        class="nav-item {{ request()->routeIs('admin.candidatures*') ? 'active' : '' }}"
                        onclick="closeSidebar()">
                        <span class="nav-icon">📥</span>
                        <span class="nav-text">Candidatures</span>
                        @if($nbCandidatures > 0)
                            <span class="nav-badge">{{ $nbCandidatures }}</span>
                        @endif
                    </a>
                @endif
            @endauth

        </div>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth()->user()->prenom ?? 'A', 0, 1)) }}
                </div>
                <div class="user-info">
                    <div class="user-name">{{ auth()->user()->prenom ?? '' }} {{ auth()->user()->nom ?? '' }}</div>
                    <div class="user-role">
                        @if(auth()->user()->isAdmin()) Administrateur
                        @elseif(auth()->user()->isGestionnaire()) Gestionnaire
                        @else Membre
                        @endif
                    </div>
                </div>
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
                <div class="flash flash-success" role="alert">
                    <span>✅</span>
                    <span>{{ session('success') }}</span>
                    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
                </div>
            @endif
            @if(session('error'))
                <div class="flash flash-error" role="alert">
                    <span>❌</span>
                    <span>{{ session('error') }}</span>
                    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
                </div>
            @endif
            @if(session('warning'))
                <div class="flash flash-warning" role="alert">
                    <span>⚠️</span>
                    <span>{{ session('warning') }}</span>
                    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
                </div>
            @endif
            @if(session('info'))
                <div class="flash flash-info" role="alert">
                    <span>ℹ️</span>
                    <span>{{ session('info') }}</span>
                    <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburgerBtn');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('visible');
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
        function toggleSidebar() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        }

        hamburger.addEventListener('click', toggleSidebar);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
    </script>

    @stack('scripts')
</body>

</html>