{{-- resources/views/emails/candidature-validee-deja-inscrit.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Accès activé — AMANA Planning</title>
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

        /* ── Header ── */
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

        .header::after {
            content: '';
            position: absolute;
            bottom: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, transparent 65%);
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

        /* ── Stripe ── */
        .stripe {
            height: 4px;
            background: repeating-linear-gradient(90deg, #0369a1 0px, #0369a1 8px, #0284c7 8px, #0284c7 10px, #0ea5e9 10px, #0ea5e9 12px, #0284c7 12px, #0284c7 14px, #0369a1 14px, #0369a1 22px, #ffffff 22px, #ffffff 28px);
        }

        /* ── Body ── */
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

        /* ── Success info box ── */
        .info-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 16px 18px;
            margin: 20px 0;
            display: table;
            width: 100%;
        }

        .info-icon {
            display: table-cell;
            font-size: 22px;
            padding-right: 14px;
            vertical-align: top;
            padding-top: 2px;
            white-space: nowrap;
        }

        .info-content {
            display: table-cell;
            vertical-align: top;
        }

        .info-title {
            font-size: 14px;
            font-weight: 600;
            color: #065f46;
            margin-bottom: 5px;
        }

        .info-text {
            font-size: 13px;
            color: #047857;
            font-weight: 300;
            line-height: 1.6;
        }

        /* ── CTA ── */
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
            letter-spacing: 0.3px;
            padding: 14px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 16px rgba(3, 105, 161, 0.35);
        }

        .cta-note {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 10px;
            font-weight: 300;
        }

        /* ── Hint box ── */
        .hint-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 13px 16px;
            display: table;
            width: 100%;
            margin: 0 0 20px;
        }

        .hint-icon {
            display: table-cell;
            font-size: 16px;
            padding-right: 10px;
            vertical-align: top;
            padding-top: 2px;
            white-space: nowrap;
        }

        .hint-text {
            display: table-cell;
            font-size: 13px;
            color: #92400e;
            font-weight: 300;
            line-height: 1.6;
            vertical-align: top;
        }

        .hint-text strong {
            color: #78350f;
            font-weight: 600;
        }

        /* ── Features card ── */
        .features-card {
            background: #0c1e2e;
            border-radius: 10px;
            padding: 22px 26px;
            margin: 24px 0;
        }

        .features-label {
            font-size: 9.5px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #7dd3fc;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .feature-row {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }

        .feature-row:last-child {
            margin-bottom: 0;
        }

        .feature-icon {
            display: table-cell;
            font-size: 18px;
            width: 28px;
            vertical-align: top;
            padding-top: 1px;
        }

        .feature-text {
            display: table-cell;
            font-size: 13.5px;
            color: #bae6fd;
            font-weight: 300;
            line-height: 1.5;
            vertical-align: top;
        }

        .feature-text strong {
            color: #7dd3fc;
            font-weight: 600;
        }

        /* ── Closing ── */
        .closing-divider {
            display: table;
            width: 100%;
            margin: 30px 0 24px;
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

        .closing-text {
            font-size: 14.5px;
            line-height: 1.85;
            color: #374151;
            font-weight: 300;
            text-align: center;
            font-style: italic;
            margin-bottom: 22px;
        }

        .closing-text strong {
            color: #0369a1;
            font-weight: 600;
            font-style: normal;
        }

        .jazakum {
            text-align: center;
        }

        .jazakum-text {
            font-size: 32px;
            font-weight: 700;
            color: #0369a1;
            direction: rtl;
            line-height: 1.6;
            font-family: 'Traditional Arabic', 'Amiri', serif;
        }

        /* ── Footer ── */
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

            .features-card {
                padding: 18px;
            }

            .footer {
                padding: 20px 18px;
                border-radius: 0;
                border-left: none;
                border-right: none;
                border-bottom: none;
            }

            .info-box,
            .hint-box {
                display: block;
            }

            .info-icon,
            .info-content,
            .hint-icon,
            .hint-text {
                display: block;
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
                <div class="header-badge">Candidature validée</div>
                <div class="header-title">Votre accès est activé&nbsp;!</div>
                <div class="header-title-sub">AMANA Planning</div>
            </div>

            <div class="stripe"></div>

            <div class="body">
                <p class="greeting">Cher <em>{{ $prenom }}</em>,</p>

                <p class="body-text">
                    Votre candidature bénévole chez <strong>AMANA</strong> a été
                    <strong>validée</strong> par l'équipe d'administration. Bienvenue dans l'équipe&nbsp;!
                </p>

                <table class="info-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="info-icon">✅</td>
                        <td class="info-content">
                            <div class="info-title">Vous avez déjà un compte AMANA</div>
                            <div class="info-text">
                                Votre adresse email est déjà associée à un compte AMANA actif.
                                Vous pouvez vous connecter directement à AMANA Planning
                                avec votre mot de passe habituel — aucune action supplémentaire n'est requise.
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="cta-wrap">
                    <a href="{{ $loginUrl }}" class="cta-button">🔐 &nbsp; Se connecter à AMANA Planning</a>
                    <p class="cta-note">Utilisez votre adresse email et votre mot de passe habituel.</p>
                </div>

                <table class="hint-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="hint-icon">💡</td>
                        <td class="hint-text">
                            Mot de passe oublié ? Rendez-vous sur la page de connexion et cliquez sur
                            <strong>« Mot de passe oublié »</strong> pour recevoir un lien de réinitialisation.
                        </td>
                    </tr>
                </table>

                <div class="features-card">
                    <div class="features-label">&#10022; &nbsp; Depuis AMANA Planning, vous pouvez</div>
                    <div class="feature-row">
                        <div class="feature-icon">📅</div>
                        <div class="feature-text"><strong>Consulter le planning</strong> des permanences vendredis &amp;
                            samedis</div>
                    </div>
                    <div class="feature-row">
                        <div class="feature-icon">📄</div>
                        <div class="feature-text"><strong>Télécharger le planning</strong> au format PDF</div>
                    </div>
                    <div class="feature-row">
                        <div class="feature-icon">🏖️</div>
                        <div class="feature-text"><strong>Déclarer vos absences</strong> pour que le planning soit
                            ajusté</div>
                    </div>
                    <div class="feature-row">
                        <div class="feature-icon">🔒</div>
                        <div class="feature-text"><strong>Gérer vos disponibilités</strong> par tâche et par jour</div>
                    </div>
                </div>

                <table class="closing-divider" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td></td>
                        <td class="star">&#10022;</td>
                        <td></td>
                    </tr>
                </table>

                <p class="closing-text">
                    Nous comptons sur votre <strong>engagement</strong> et votre <strong>générosité</strong><br>
                    pour que notre mission collective soit une réussite.
                </p>

                <div class="jazakum">
                    <p class="jazakum-text">جزاكم الله خيرا</p>
                </div>
            </div>

            <div class="footer">
                <div class="footer-logo">AMANA</div>
                <div style="width:32px;height:1px;background:#c7dff0;margin:0 auto 10px;"></div>
                <p>
                    Vous recevez cet email suite à la validation de votre candidature bénévole.<br>
                    Pour toute question&nbsp;: <a
                        href="mailto:amana44.benevole@gmail.com">amana44.benevole@gmail.com</a>
                </p>
            </div>

        </div>
    </div>
</body>

</html>