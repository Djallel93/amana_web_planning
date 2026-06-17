{{-- resources/views/auth/forgot-password.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — AMANA Planning</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body>

    <div class="panel-left">
        <div class="panel-left-content">
            <div class="big-logo">
                <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA">
            </div>
            <div class="big-title">AMANA Planning</div>
            <div class="big-subtitle">Réinitialisez votre mot de passe en quelques secondes.</div>
        </div>
    </div>

    <div class="panel-right">
        <div class="auth-box">
            <div class="auth-title">Mot de passe oublié</div>
            <div class="auth-sub">
                Saisissez votre adresse email. Si elle est connue, vous recevrez un lien pour réinitialiser votre mot de
                passe.
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
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
                        autofocus placeholder="votre@email.fr">
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn-submit">📧 Envoyer le lien de réinitialisation</button>
            </form>

            <a href="{{ route('login') }}" class="auth-link">← Retour à la connexion</a>
        </div>
    </div>

</body>

</html>