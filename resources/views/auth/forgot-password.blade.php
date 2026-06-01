{{-- resources/views/auth/forgot-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — AMANA Planning</title>
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
        .flash-success { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
        .flash-error   { background:#fff1f2; border-color:#fecdd3; color:#9f1239; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 12.5px; font-weight: 700; color: var(--ink); margin-bottom: 7px; }
        .form-group input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 14px; font-family: inherit; color: var(--ink);
            background: var(--surface-2); outline: none; transition: all 0.18s;
        }
        .form-group input:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(79,70,229,0.12); }
        .field-error { color: var(--rose); font-size: 12px; margin-top: 5px; }
        .btn-submit {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--violet) 100%);
            color: white; border: none; border-radius: 10px;
            font-size: 14.5px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(79,70,229,0.4);
            margin-bottom: 16px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,70,229,0.5); }
        .back-link { display: block; text-align: center; font-size: 13.5px; color: var(--ink-muted); text-decoration: none; }
        .back-link:hover { color: var(--primary); }
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
        <div class="big-logo">📅</div>
        <div class="big-title">AMANA Planning</div>
        <div class="big-subtitle">Réinitialisez votre mot de passe en quelques secondes.</div>
    </div>
</div>

<div class="panel-right">
    <div class="box">
        <div class="box-title">Mot de passe oublié</div>
        <div class="box-sub">
            Saisissez votre adresse email. Si elle est connue, vous recevrez un lien pour réinitialiser votre mot de passe.
        </div>

        @if(session('success'))
            <div class="flash flash-success">✅ {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash flash-error">❌ {{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       autocomplete="email" autofocus
                       placeholder="votre@email.fr">
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn-submit">
                📧 Envoyer le lien de réinitialisation
            </button>
        </form>

        <a href="{{ route('login') }}" class="back-link">← Retour à la connexion</a>
    </div>
</div>

</body>
</html>
