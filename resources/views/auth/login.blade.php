{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — AMANA Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
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

        .remember-wrap input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--app-accent);
            cursor: pointer;
            -webkit-appearance: auto;
            appearance: auto;
        }

        .remember-wrap label {
            cursor: pointer;
            font-size: 13px;
        }

        .forgot-link {
            font-size: 13px;
            color: var(--app-accent);
            font-weight: 600;
            transition: color 0.15s;
            white-space: nowrap;
        }

        .forgot-link:hover {
            color: #0284c7;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="panel-left">
        <div class="panel-left-content">
            <div class="big-logo">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA">
            </div>
            <div class="big-title">AMANA Planning</div>
            <div class="big-subtitle">Planification des permanences et rotation équitable des tâches</div>
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
        <div class="auth-box">
            <div class="auth-title">Connexion</div>
            <div class="auth-sub">Accédez à votre espace de gestion</div>

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

                <div class="remember-row">
                    <div class="remember-wrap">
                        <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="forgot-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-submit">🔐 Se connecter</button>
            </form>

            <div class="auth-divider"></div>
            <p class="auth-link">
                Pas encore de compte ?
                <a href="{{ route('inscription') }}"><span>Soumettre une candidature</span></a>
            </p>
        </div>
    </div>

</body>

</html>