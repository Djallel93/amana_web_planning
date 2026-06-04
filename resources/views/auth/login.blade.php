{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — AMANA Planning</title>

    {{-- Normalize.css --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --violet: #7c3aed;
            --ink: #0f1117;
            --ink-muted: #7a7f94;
            --surface: #ffffff;
            --surface-2: #f4f5f9;
            --border: #c4c8d8;
            --rose: #e11d48;
            --emerald: #059669;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: var(--surface-2);
        }

        /* ── Left panel ── */
        .panel-left {
            flex: 1;
            background: var(--ink);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            top: -120px;
            left: -120px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.4) 0%, transparent 65%);
            pointer-events: none;
        }

        .panel-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            right: -80px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.3) 0%, transparent 65%);
            pointer-events: none;
        }

        .panel-left-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 380px;
        }

        .big-logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--violet));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 24px;
            box-shadow: 0 16px 40px rgba(79, 70, 229, 0.45);
        }

        .big-title {
            font-family: 'DM Serif Display', serif;
            font-size: 36px;
            color: white;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .big-subtitle {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.6;
        }

        .features {
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            text-align: left;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .feature-icon {
            width: 34px;
            height: 34px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        /* ── Right panel ── */
        .panel-right {
            width: 480px;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 56px;
            flex-shrink: 0;
        }

        .login-box {
            width: 100%;
            max-width: 360px;
        }

        .login-title {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .login-sub {
            font-size: 14px;
            color: var(--ink-muted);
            margin-bottom: 32px;
        }

        .flash {
            padding: 12px 16px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid;
        }

        .flash-error {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #9f1239;
        }

        .flash-success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 7px;
            letter-spacing: 0.2px;
        }

        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: var(--ink);
            background: var(--surface-2);
            outline: none;
            transition: all 0.18s;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
            font-size: 16px;
            /* prevent iOS zoom */
        }

        .field-error {
            color: var(--rose);
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        .login-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 8px;
            flex-wrap: wrap;
        }

        .remember-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ink-muted);
        }

        .remember-wrap input {
            width: auto;
            accent-color: var(--primary);
            cursor: pointer;
            padding: 0;
            background: none;
            border: none;
        }

        .remember-wrap label {
            cursor: pointer;
            font-weight: 400;
            margin-bottom: 0;
        }

        .forgot-link {
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
            white-space: nowrap;
        }

        .forgot-link:hover {
            color: var(--violet);
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
            margin-bottom: 20px;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.5);
        }

        .btn-login:active {
            transform: none;
        }

        .register-link {
            text-align: center;
            font-size: 13.5px;
            color: var(--ink-muted);
            padding-top: 16px;
            border-top: 1px solid var(--surface-2);
        }

        .register-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        /* ── Responsive ── */

        /* Tablet: shrink left panel */
        @media (max-width: 900px) {
            .features {
                display: none;
            }

            .panel-left {
                padding: 40px 32px;
            }

            .big-title {
                font-size: 28px;
            }

            .panel-right {
                width: 400px;
                padding: 40px 36px;
            }
        }

        /* Mobile: stack vertically, hide left panel */
        @media (max-width: 640px) {
            body {
                flex-direction: column;
                min-height: 100vh;
            }

            .panel-left {
                display: none;
            }

            .panel-right {
                width: 100%;
                flex: 1;
                padding: 40px 24px 32px;
                align-items: flex-start;
                padding-top: 48px;
            }

            .login-box {
                max-width: 100%;
            }

            .login-title {
                font-size: 28px;
            }
        }

        @media (max-width: 380px) {
            .panel-right {
                padding: 32px 16px;
            }

            .login-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>

<body>

    <div class="panel-left">
        <div class="panel-left-content">
            <div class="big-logo">📅</div>
            <div class="big-title">AMANA Planning</div>
            <div class="big-subtitle">Système de planification des permanences et rotation des tâches</div>
            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">🔄</div>
                    <span>Rotation automatique équitable des tâches</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">📊</div>
                    <span>Statistiques et score d'équité</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">📄</div>
                    <span>Export PDF du planning</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">↩️</div>
                    <span>Rollback et gestion des absences</span>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-right">
        <div class="login-box">
            <div class="login-title">Connexion</div>
            <div class="login-sub">Accédez à votre espace de gestion</div>

            @if(session('success'))
                <div class="flash flash-success">✅ {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash flash-error">❌ {{ session('error') }}</div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST" novalidate>
                @csrf
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
                        autofocus placeholder="votre@email.fr">
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" autocomplete="current-password"
                        placeholder="••••••••">
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="login-meta">
                    <div class="remember-wrap">
                        <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-login">🔐 Se connecter</button>
            </form>

            <div class="register-link">
                Pas encore de compte ?
                <a href="{{ route('inscription') }}">Soumettre une candidature</a>
            </div>
        </div>
    </div>

</body>

</html>