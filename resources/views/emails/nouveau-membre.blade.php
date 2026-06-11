{{-- resources/views/emails/nouveau-membre.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Nouvelle candidature — AMANA</title>
    <style>
        :root {
            color-scheme: light only;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f4f6f8 !important;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a2332;
            -webkit-font-smoothing: antialiased;
            margin: 0 !important;
            padding: 0 !important;
        }

        .shell {
            background: #f4f6f8;
            padding: 32px 16px 48px;
        }

        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: #0c1e2e;
            padding: 40px 32px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -80px;
            left: -80px;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(3, 105, 161, 0.35) 0%, transparent 65%);
            pointer-events: none;
        }

        .header-logo {
            width: 72px;
            height: 72px;
            border-radius: 16px;
            overflow: hidden;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .header-brand {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .header-sub {
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 500;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .header-divider {
            display: table;
            width: 100%;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }

        .header-divider td {
            border-top: 1px solid rgba(14, 165, 233, 0.3);
            vertical-align: middle;
        }

        .header-divider .star {
            padding: 0 12px;
            color: rgba(14, 165, 233, 0.7);
            font-size: 14px;
            white-space: nowrap;
        }

        .header-badge {
            display: inline-block;
            background: rgba(14, 165, 233, 0.15);
            border: 1px solid rgba(14, 165, 233, 0.3);
            border-radius: 40px;
            padding: 5px 18px;
            font-size: 10px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #7dd3fc;
            font-weight: 600;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .header-title-sub {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 300;
            position: relative;
            z-index: 1;
        }

        .stripe {
            height: 4px;
            background: repeating-linear-gradient(90deg, #0369a1 0px, #0369a1 8px, #0284c7 8px, #0284c7 10px, #0ea5e9 10px, #0ea5e9 12px, #0284c7 12px, #0284c7 14px, #0369a1 14px, #0369a1 22px, #ffffff 22px, #ffffff 28px);
        }

        .body {
            background: #ffffff;
            padding: 36px 32px;
            border-left: 1px solid #e5e9ef;
            border-right: 1px solid #e5e9ef;
        }

        .greeting {
            font-size: 20px;
            font-weight: 600;
            color: #1a2332;
            margin-bottom: 18px;
            line-height: 1.35;
        }

        .greeting em {
            color: #0369a1;
            font-style: italic;
        }

        .body-text {
            font-size: 14.5px;
            line-height: 1.85;
            color: #374151;
            font-weight: 300;
            margin-bottom: 16px;
        }

        .body-text strong {
            color: #0c4a6e;
            font-weight: 600;
        }

        .candidat-card {
            background: #0c1e2e;
            border-radius: 10px;
            padding: 22px 26px;
            margin: 24px 0;
        }

        .candidat-label {
            font-size: 9.5px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #7dd3fc;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 11px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-icon-cell {
            display: table-cell;
            font-size: 16px;
            width: 26px;
            vertical-align: top;
            padding-top: 2px;
        }

        .info-text-cell {
            display: table-cell;
            font-size: 14px;
            color: #bae6fd;
            font-weight: 300;
            line-height: 1.4;
            vertical-align: top;
        }

        .info-text-cell strong {
            color: #7dd3fc;
            font-weight: 600;
        }

        .restrictions-label {
            font-size: 9.5px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #0369a1;
            font-weight: 600;
            text-align: center;
            margin: 24px 0 12px;
        }

        .restrictions-grid {
            display: table;
            width: 100%;
            border-spacing: 10px;
        }

        .restriction-day {
            display: table-cell;
            width: 50%;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 13px 15px;
            vertical-align: top;
        }

        .restriction-day-title {
            font-size: 11px;
            font-weight: 700;
            color: #1a2332;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 9px;
            padding-bottom: 7px;
            border-bottom: 1px solid #bae6fd;
        }

        .restriction-item {
            display: table;
            width: 100%;
            font-size: 12.5px;
            color: #374151;
            padding: 3px 0;
            font-weight: 300;
        }

        .restriction-item-label {
            display: table-cell;
            vertical-align: middle;
        }

        .restriction-item-badge {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
            white-space: nowrap;
        }

        .pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 600;
        }

        .pill-yes {
            background: #dcfce7;
            color: #166534;
        }

        .pill-no {
            background: #fee2e2;
            color: #991b1b;
        }

        .cta-wrap {
            text-align: center;
            margin: 28px 0;
        }

        .cta-button {
            display: inline-block;
            background: #0369a1;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 14px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(3, 105, 161, 0.35);
        }

        .closing-divider {
            display: table;
            width: 100%;
            margin: 24px 0 8px;
        }

        .closing-divider td {
            border-top: 1px solid #e5e9ef;
            vertical-align: middle;
        }

        .closing-divider .star {
            padding: 0 14px;
            color: #0369a1;
            font-size: 13px;
            white-space: nowrap;
        }

        .footer {
            background: #f0f6fb;
            border: 1px solid #c7dff0;
            border-top: none;
            padding: 24px 32px;
            text-align: center;
        }

        .footer-logo {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #0369a1;
            margin-bottom: 10px;
        }

        .footer p {
            font-size: 11.5px;
            color: #6b7280;
            line-height: 1.9;
            font-weight: 300;
        }

        .footer a {
            color: #0369a1;
            text-decoration: none;
        }

        @media only screen and (max-width:480px) {
            .shell {
                padding: 0;
            }

            .header {
                padding: 32px 18px 28px;
                border-radius: 0;
            }

            .body {
                padding: 26px 18px;
                border-left: none;
                border-right: none;
            }

            .candidat-card {
                padding: 18px;
            }

            .restrictions-grid {
                display: block;
            }

            .restriction-day {
                display: block;
                width: 100%;
                margin-bottom: 10px;
            }

            .footer {
                padding: 20px 18px;
                border-radius: 0;
                border-left: none;
                border-right: none;
                border-bottom: none;
            }
        }
    </style>
</head>

<body>
    <div class="shell">
        <div class="wrapper">

            <div class="header">
                <div class="header-logo">
                    <img src="{{ config('app.url') }}/images/amana-logo.png" alt="AMANA">
                </div>
                <div class="header-brand">AMANA</div>
                <div class="header-sub">Association Musulmane de l'Agglomération Nantaise et ses Alentours</div>
                <table class="header-divider" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td></td>
                        <td class="star">&#10022;</td>
                        <td></td>
                    </tr>
                </table>
                <div class="header-badge">Administration</div>
                <div class="header-title">Nouvelle candidature</div>
                <div class="header-title-sub">En attente de validation</div>
            </div>

            <div class="stripe"></div>

            <div class="body">
                <p class="greeting">Bonjour <em>{{ $adminPrenom }}</em>,</p>
                <p class="body-text">
                    Une nouvelle candidature bénévole vient d'être soumise sur
                    <strong>AMANA Planning</strong> et attend votre validation.
                </p>

                <div class="candidat-card">
                    <div class="candidat-label">&#10022; &nbsp; Informations du candidat</div>
                    <div class="info-row">
                        <div class="info-icon-cell">👤</div>
                        <div class="info-text-cell"><strong>{{ $candidat->prenom }}
                                {{ strtoupper($candidat->nom) }}</strong></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon-cell">✉️</div>
                        <div class="info-text-cell">{{ $candidat->email }}</div>
                    </div>
                    @if($candidat->telephone)
                        <div class="info-row">
                            <div class="info-icon-cell">📞</div>
                            <div class="info-text-cell">{{ $candidat->telephone }}</div>
                        </div>
                    @endif
                </div>

                @if($candidat->restrictions->isNotEmpty())
                    <div class="restrictions-label">&#10022; &nbsp; Disponibilités déclarées &nbsp; &#10022;</div>
                    <table class="restrictions-grid" role="presentation" cellpadding="5" cellspacing="0" border="0">
                        <tr>
                            @foreach(['Vendredi', 'Samedi'] as $jour)
                                <td class="restriction-day">
                                    <div class="restriction-day-title">{{ $jour }}</div>
                                    @foreach($candidat->restrictions->where('jour', $jour) as $r)
                                        <table class="restriction-item" role="presentation" cellpadding="0" cellspacing="0"
                                            border="0">
                                            <tr>
                                                <td class="restriction-item-label">{{ $r->tache->libelle ?? '—' }}</td>
                                                <td class="restriction-item-badge">
                                                    <span class="pill {{ $r->autorise ? 'pill-yes' : 'pill-no' }}">
                                                        {{ $r->autorise ? '✓ Dispo' : '✗ Indispo' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    @endforeach
                                    @if($candidat->restrictions->where('jour', $jour)->isEmpty())
                                        <div style="font-size:12px;color:#9ca3af;font-style:italic;">Non renseigné</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif

                <div class="cta-wrap">
                    <a href="{{ $urlValidation }}" class="cta-button">📥 &nbsp; Voir les candidatures en attente</a>
                </div>

                <table class="closing-divider" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td></td>
                        <td class="star">&#10022;</td>
                        <td></td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                <div class="footer-logo">AMANA</div>
                <div style="width:32px;height:1px;background:#c7dff0;margin:0 auto 10px;"></div>
                <p>
                    Vous recevez cet email en tant qu'administrateur d'AMANA Planning.<br>
                    Pour toute question : <a href="mailto:amana44.benevole@gmail.com">amana44.benevole@gmail.com</a>
                </p>
            </div>

        </div>
    </div>
</body>

</html>