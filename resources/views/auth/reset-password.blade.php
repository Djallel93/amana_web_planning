{{-- resources/views/auth/reset-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer mon mot de passe — AMANA Planning</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:   #4f46e5;
            --violet:    #7c3aed;
            --ink:       #0f1117;
            --ink-muted: #7a7f94;
            --surface:   #ffffff;
            --surface-2: #f4f5f9;
            --border:    #c4c8d8;
            --rose:      #e11d48;
            --emerald:   #059669;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 480px;
            -webkit-font-smoothing: antialiased;
        }
        .panel-left {
            background: var(--ink);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
            position: relative;
            overflow: hidden;
        }
        .panel-left::before {
            content: '';
            position: absolute;
            top: -120px; left: -120px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(79,70,229,0.4) 0%, transparent 65%);
        }
        .panel-left::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(124,58,237,0.3) 0%, transparent 65%);
        }
        .panel-left-content { position: relative; z-index: 1; text-align: center; max-width: 380px; }
        .big-logo {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--violet));
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px;
            margin: 0 auto 24px;
            box-shadow: 0 16px 40px rgba(79,70,229,0.45);
        }
        .big-title { font-family: 'DM Serif Display', serif; font-size: 36px; color: white; margin-bottom: 12px; }
        .big-subtitle { font-size: 15px; color: rgba(255,255,255,0.5); line-height: 1.6; }
        .rules {
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: left;
        }
        .rule-item {
            display: flex; align-items: center; gap: 10px;
            color: rgba(255,255,255,0.6); font-size: 13.5px;
        }
        .rule-icon {
            width: 28px; height: 28px;
            background: rgba(255,255,255,0.08);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; flex-shrink: 0;
        }
        .panel-right {
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 56px;
        }
        .box { width: 100%; max-width: 360px; }
        .box-title { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--ink); margin-bottom: 6px; }
        .box-sub { font-size: 14px; color: var(--ink-muted); margin-bottom: 32px; line-height: 1.6; }
        .flash {
            padding: 12px 16px; border-radius: 9px; font-size: 13px;
            font-weight: 500; margin-bottom: 20px;
            display: flex; align-items: center; gap: 9px; border: 1px solid;
        }
        .flash-error { background:#fff1f2; border-color:#fecdd3; color:#9f1239; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12.5px; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .form-group input {
            width: 100%; padding: 10px 40px 10px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 14px; font-family: inherit; color: var(--ink);
            background: var(--surface-2); outline: none; transition: all 0.18s;
        }
        .form-group input:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(79,70,229,0.12); }
        .toggle-pwd {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--ink-muted); font-size: 16px; padding: 2px;
            transition: color 0.15s;
        }
        .toggle-pwd:hover { color: var(--ink); }
        .field-error { color: var(--rose); font-size: 12px; margin-top: 5px; }

        /* Indicateur de force du mot de passe */
        .strength-wrap { margin-top: 8px; }
        .strength-bar {
            height: 4px; border-radius: 2px;
            background: var(--surface-2);
            overflow: hidden; margin-bottom: 4px;
        }
        .strength-fill {
            height: 100%; border-radius: 2px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }
        .strength-label { font-size: 11.5px; color: var(--ink-muted); }

        .btn-submit {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white; border: none; border-radius: 10px;
            font-size: 14.5px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(79,70,229,0.4);
            margin-top: 8px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,0.5); }
        @media (max-width: 768px) {
            body { grid-template-columns: 1fr; }
            .panel-left { display: none; }
            .panel-right { padding: 32px 24px; }
        }
    </style>
</head>
<body>

<div class="panel-left">
    <div class="panel-left-content">
        <div class="big-logo">🔐</div>
        <div class="big-title">AMANA Planning</div>
        <div class="big-subtitle">Choisissez un mot de passe sécurisé pour protéger votre compte.</div>
        <div class="rules">
            <div class="rule-item">
                <div class="rule-icon">✅</div>
                <span>Au moins 8 caractères</span>
            </div>
            <div class="rule-item">
                <div class="rule-icon">✅</div>
                <span>Mélangez majuscules et minuscules</span>
            </div>
            <div class="rule-item">
                <div class="rule-icon">✅</div>
                <span>Ajoutez des chiffres ou symboles</span>
            </div>
            <div class="rule-item">
                <div class="rule-icon">✅</div>
                <span>Évitez les informations personnelles</span>
            </div>
        </div>
    </div>
</div>

<div class="panel-right">
    <div class="box">
        {{-- Le titre change selon le contexte --}}
        @if(request()->routeIs('password.reset') && !empty($email))
            <div class="box-title">Créer mon mot de passe</div>
            <div class="box-sub">Bienvenue ! Choisissez un mot de passe pour accéder à AMANA Planning.</div>
        @else
            <div class="box-title">Nouveau mot de passe</div>
            <div class="box-sub">Choisissez un nouveau mot de passe pour votre compte.</div>
        @endif

        @if($errors->any())
            <div class="flash flash-error">❌ {{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.update') }}" method="POST">
            @csrf

            {{-- Token et email passés en champs cachés --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $email) }}"
                           autocomplete="email" required>
                </div>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password"
                           autocomplete="new-password"
                           placeholder="Au moins 8 caractères"
                           oninput="checkStrength(this.value)"
                           required>
                    <button type="button" class="toggle-pwd" onclick="toggleVisibility('password', this)">👁️</button>
                </div>
                <div class="strength-wrap">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <div class="input-wrap">
                    <input type="password" id="password_confirmation"
                           name="password_confirmation"
                           autocomplete="new-password"
                           placeholder="Répétez le mot de passe"
                           required>
                    <button type="button" class="toggle-pwd" onclick="toggleVisibility('password_confirmation', this)">👁️</button>
                </div>
                @error('password_confirmation')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn-submit">
                🔐 Enregistrer mon mot de passe
            </button>
        </form>
    </div>
</div>

<script>
function toggleVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁️';
    }
}

function checkStrength(password) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');

    if (!password) {
        fill.style.width = '0%';
        label.textContent = '';
        return;
    }

    let score = 0;
    if (password.length >= 8)  score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    const levels = [
        { pct: '20%', color: '#e11d48', text: 'Très faible' },
        { pct: '40%', color: '#f59e0b', text: 'Faible' },
        { pct: '60%', color: '#eab308', text: 'Moyen' },
        { pct: '80%', color: '#22c55e', text: 'Fort' },
        { pct: '100%',color: '#059669', text: 'Très fort' },
    ];

    const level = levels[Math.min(score - 1, 4)] || levels[0];
    fill.style.width      = level.pct;
    fill.style.background = level.color;
    label.textContent     = level.text;
    label.style.color     = level.color;
}
</script>

</body>
</html>
