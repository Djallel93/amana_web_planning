{{-- resources/views/emergency/hash.blade.php --}}
{{--
Vue d'urgence post-déploiement.
Accessible uniquement si APP_EMERGENCY_KEY est défini dans .env.
Ne modifie rien en base — génère un hash bcrypt à coller dans phpMyAdmin.
--}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outil d'urgence — AMANA</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #fef2f2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: white;
            border: 2px solid #fca5a5;
            border-radius: 12px;
            padding: 32px;
            max-width: 540px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .icon {
            font-size: 28px;
            flex-shrink: 0;
        }

        h1 {
            font-size: 18px;
            font-weight: 700;
            color: #991b1b;
        }

        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            color: #111827;
            outline: none;
            transition: border-color 0.15s;
        }

        input[type="password"]:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
        }

        .error {
            font-size: 12px;
            color: #dc2626;
            margin-top: 4px;
        }

        button {
            width: 100%;
            padding: 11px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 8px;
        }

        button:hover {
            background: #b91c1c;
        }

        .result {
            margin-top: 24px;
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 8px;
            padding: 20px;
        }

        .result-title {
            font-size: 13px;
            font-weight: 700;
            color: #166534;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hash-box {
            background: #1e293b;
            color: #86efac;
            font-family: 'Courier New', monospace;
            font-size: 11.5px;
            padding: 14px;
            border-radius: 6px;
            word-break: break-all;
            line-height: 1.5;
            margin-bottom: 12px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.15s;
        }

        .hash-box:hover {
            border-color: #86efac;
        }

        .copy-hint {
            font-size: 11.5px;
            color: #166534;
            margin-bottom: 14px;
        }

        .sql-box {
            background: #1e293b;
            color: #93c5fd;
            font-family: 'Courier New', monospace;
            font-size: 11.5px;
            padding: 14px;
            border-radius: 6px;
            word-break: break-all;
            line-height: 1.6;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.15s;
        }

        .sql-box:hover {
            border-color: #93c5fd;
        }

        .sql-label {
            font-size: 12px;
            font-weight: 600;
            color: #166534;
            margin: 12px 0 6px;
        }

        .disable-note {
            margin-top: 20px;
            padding: 12px 14px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            font-size: 12px;
            color: #991b1b;
            line-height: 1.6;
        }

        .copied-toast {
            display: none;
            font-size: 12px;
            color: #166534;
            font-weight: 600;
            margin-top: 6px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <span class="icon">🔐</span>
            <h1>Outil d'urgence — Génération de hash</h1>
        </div>

        <div class="warning">
            ⚠️ <strong>Outil d'urgence post-déploiement.</strong><br>
            Cet outil génère un hash bcrypt à copier manuellement dans phpMyAdmin.
            <strong>Aucune modification de la base de données n'est effectuée ici.</strong><br>
            Désactivez-le après usage en retirant <code>APP_EMERGENCY_KEY</code> du <code>.env</code>.
        </div>

        <form method="POST" action="{{ route('emergency.hash.generate') }}">
            @csrf
            <input type="hidden" name="key" value="{{ $key }}">

            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" required minlength="8"
                    placeholder="8 caractères minimum" autocomplete="new-password">
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    placeholder="Répéter le mot de passe" autocomplete="new-password">
            </div>

            <button type="submit">🔑 Générer le hash bcrypt</button>
        </form>

        @if($hash)
            <div class="result">
                <div class="result-title">✅ Hash généré — cliquez pour copier</div>

                <div class="hash-box" onclick="copyToClipboard(this, '{{ addslashes($hash) }}')"
                    title="Cliquer pour copier">{{ $hash }}</div>
                <div class="copy-hint">👆 Cliquez sur le hash pour le copier dans le presse-papiers.</div>

                <div class="sql-label">Requête SQL à exécuter dans phpMyAdmin :</div>
                @php
                    $sql = "UPDATE ref_personnes SET password = '" . addslashes($hash) . "' WHERE email = 'votre@email.com';";
                @endphp
                <div class="sql-box" onclick="copyToClipboard(this, {{ json_encode($sql) }})" title="Cliquer pour copier">
                    UPDATE ref_personnes<br>
                    SET password = '<span style="color:#fde68a;">{{ $hash }}</span>'<br>
                    WHERE email = '<span style="color:#fde68a;">votre@email.com</span>';
                </div>
                <div class="copy-hint" style="margin-top:6px;">
                    ⚠️ Remplacez <strong>votre@email.com</strong> par l'email réel du compte à mettre à jour.
                </div>

                <div class="disable-note">
                    🔒 <strong>Désactiver après usage :</strong> retirez <code>APP_EMERGENCY_KEY</code>
                    du <code>.env</code>, puis exécutez <code>php artisan config:cache</code>
                    ou redéployez.
                </div>
            </div>
        @endif
    </div>

    <script>
        function copyToClipboard(el, text) {
            navigator.clipboard.writeText(text).then(function () {
                const orig = el.style.borderColor;
                el.style.borderColor = '#22c55e';
                setTimeout(() => el.style.borderColor = orig, 1500);
            }).catch(function () {
                // Fallback pour navigateurs sans clipboard API
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            });
        }
    </script>
</body>

</html>