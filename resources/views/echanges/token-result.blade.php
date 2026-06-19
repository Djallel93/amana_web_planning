{{-- resources/views/echanges/token-result.blade.php --}}
{{-- Page publique affichée après clic sur le lien accept/refuse dans l'email --}}
{{-- Pas de layout app (pas forcément connecté) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if($success && $action === 'accepte') Échange confirmé
        @elseif($success && $action === 'refuse') Échange refusé
        @else Lien invalide
        @endif
        — AMANA Planning
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--surface-2);
            padding: 24px;
        }

        .result-card {
            background: var(--surface);
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(13, 17, 23, 0.1);
            border: 1px solid var(--surface-border);
            padding: 48px 44px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .result-icon {
            font-size: 56px;
            margin-bottom: 20px;
            display: block;
        }

        .result-title {
            font-family: var(--font-heading);
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 10px;
            letter-spacing: -0.3px;
        }

        .result-sub {
            font-size: 14.5px;
            color: var(--ink-muted);
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .swap-summary {
            background: var(--surface-2);
            border: 1px solid var(--surface-border);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 28px;
            text-align: left;
        }

        .swap-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: var(--ink-light);
            padding: 6px 0;
            border-bottom: 1px solid var(--surface-3);
        }

        .swap-row:last-child { border-bottom: none; }

        .swap-row-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--ink-muted);
            width: 90px;
            flex-shrink: 0;
        }

        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: var(--app-accent);
            color: white;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(3,105,161,0.3);
            transition: all 0.18s;
        }

        .btn-login:hover {
            background: #0284c7;
            transform: translateY(-1px);
        }

        .amana-brand {
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .amana-brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            object-fit: cover;
        }

        .amana-brand-name {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
        }

        @media (max-width: 520px) {
            .result-card { padding: 32px 20px; }
            .result-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="result-card">
        <div class="amana-brand">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="amana-brand-logo">
            <span class="amana-brand-name">AMANA Planning</span>
        </div>

        @if($success && $action === 'accepte')
            <span class="result-icon">✅</span>
            <div class="result-title">Échange confirmé&nbsp;!</div>
            <div class="result-sub">
                L'échange a bien été effectué. Les deux membres ont été notifiés par email
                et leurs plannings ont été mis à jour.
            </div>

            @if(isset($echange))
                <div class="swap-summary">
                    <div class="swap-row">
                        <span class="swap-row-label">Créneau A</span>
                        <span>
                            {{ $echange->creneauDemandeur->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            · {{ $echange->tacheDemandeur->libelle }}
                        </span>
                    </div>
                    <div class="swap-row">
                        <span class="swap-row-label">Créneau B</span>
                        <span>
                            {{ $echange->creneauCible->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            · {{ $echange->tacheCible->libelle }}
                        </span>
                    </div>
                </div>
            @endif

        @elseif($success && $action === 'refuse')
            <span class="result-icon">✕</span>
            <div class="result-title">Échange refusé</div>
            <div class="result-sub">
                Vous avez refusé la demande d'échange.
                @if(isset($echange))
                    <strong>{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>
                    a été notifié.
                @endif
                Votre planning reste inchangé.
            </div>

        @else
            <span class="result-icon">⚠️</span>
            <div class="result-title">Lien invalide</div>
            <div class="result-sub">
                {{ $message ?? 'Ce lien est invalide, a déjà été utilisé, ou a expiré.' }}
            </div>
        @endif

        <a href="{{ $urlLogin }}" class="btn-login">
            🔐 Accéder à mon planning
        </a>
    </div>
</body>
</html>
