{{-- resources/views/auth/reset-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer mon mot de passe — AMANA Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            padding-right: 42px;
        }

        .toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted);
            font-size: 15px;
            padding: 2px;
            transition: color 0.15s;
            line-height: 1;
        }

        .toggle-pwd:hover {
            color: var(--ink);
        }

        .strength-wrap {
            margin-top: 7px;
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: #e5e7eb;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .strength-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }

        .strength-label {
            font-size: 11.5px;
            color: var(--ink-muted);
        }

        .rules {
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 9px;
            text-align: left;
        }

        .rule-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.55);
            font-size: 13px;
        }

        .rule-icon {
            width: 26px;
            height: 26px;
            background: rgba(255, 255, 255, 0.07);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
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
            <div class="big-subtitle">Choisissez un mot de passe sécurisé pour protéger votre compte.</div>
            <div class="rules">
                <div class="rule-item">
                    <div class="rule-icon">✅</div><span>Au moins 8 caractères</span>
                </div>
                <div class="rule-item">
                    <div class="rule-icon">✅</div><span>Mélangez majuscules et minuscules</span>
                </div>
                <div class="rule-item">
                    <div class="rule-icon">✅</div><span>Ajoutez des chiffres ou symboles</span>
                </div>
                <div class="rule-item">
                    <div class="rule-icon">✅</div><span>Évitez les informations personnelles</span>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-right">
        <div class="auth-box">
            @if(request()->routeIs('password.reset') && !empty($email))
                <div class="auth-title">Créer mon mot de passe</div>
                <div class="auth-sub">Bienvenue ! Choisissez un mot de passe pour accéder à AMANA Planning.</div>
            @else
                <div class="auth-title">Nouveau mot de passe</div>
                <div class="auth-sub">Choisissez un nouveau mot de passe pour votre compte.</div>
            @endif

            @if($errors->any())
                <div class="flash flash-error">❌ {{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" autocomplete="email"
                        required>
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" autocomplete="new-password"
                            placeholder="Au moins 8 caractères" oninput="checkStrength(this.value)" required>
                        <button type="button" class="toggle-pwd"
                            onclick="toggleVisibility('password', this)">👁️</button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <div class="input-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                            autocomplete="new-password" placeholder="Répétez le mot de passe" required>
                        <button type="button" class="toggle-pwd"
                            onclick="toggleVisibility('password_confirmation', this)">👁️</button>
                    </div>
                    @error('password_confirmation')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <button type="submit" class="btn-submit" style="margin-top:8px;">🔐 Enregistrer mon mot de
                    passe</button>
            </form>
        </div>
    </div>

    <script>
        function toggleVisibility(id, btn) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.textContent = input.type === 'password' ? '👁️' : '🙈';
        }
        function checkStrength(password) {
            const fill = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');
            if (!password) { fill.style.width = '0%'; label.textContent = ''; return; }
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            const levels = [
                { pct: '20%', color: '#e11d48', text: 'Très faible' },
                { pct: '40%', color: '#f59e0b', text: 'Faible' },
                { pct: '60%', color: '#eab308', text: 'Moyen' },
                { pct: '80%', color: '#22c55e', text: 'Fort' },
                { pct: '100%', color: '#059669', text: 'Très fort' },
            ];
            const level = levels[Math.min(score - 1, 4)] || levels[0];
            fill.style.width = level.pct;
            fill.style.background = level.color;
            label.textContent = level.text;
            label.style.color = level.color;
        }
    </script>
</body>

</html>