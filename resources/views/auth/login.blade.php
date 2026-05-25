{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — AMANA Planning</title>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --danger: #f56565;
            --border: #e2e8f0;
            --text: #2d3748;
            --text-muted: #718096;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: white;
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo-icon { font-size: 48px; display: block; margin-bottom: 8px; }
        .logo h1 { font-size: 24px; font-weight: 700; color: var(--text); }
        .logo p  { font-size: 14px; color: var(--text-muted); margin-top: 4px; }

        .flash-error {
            background: #fff5f5;
            border-left: 4px solid var(--danger);
            color: #822727;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .flash-success {
            background: #f0fff4;
            border-left: 4px solid #48bb78;
            color: #276749;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text);
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.12);
        }
        .field-error {
            color: var(--danger);
            font-size: 12px;
            margin-top: 4px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            font-size: 13px;
            color: var(--text-muted);
            cursor: pointer;
        }
        .remember input { cursor: pointer; accent-color: var(--primary); }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.5);
        }
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <span class="logo-icon">📅</span>
        <h1>AMANA Planning</h1>
        <p>Système de planification des permanences</p>
    </div>

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="flash-success">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash-error">❌ {{ session('error') }}</div>
    @endif

    <form action="{{ route('login.submit') }}" method="POST" novalidate>
        @csrf

        <div class="form-group">
            <label for="email">Adresse email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="email"
                autofocus
                placeholder="admin@amana.fr"
            >
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                placeholder="••••••••"
            >
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <label class="remember">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            Se souvenir de moi
        </label>

        <button type="submit" class="btn-login">🔐 Se connecter</button>
    </form>
</div>
</body>
</html>
