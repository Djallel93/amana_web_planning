{{-- resources/views/emails/candidature-validee.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>Bienvenue chez AMANA</title>
    <style>
        :root {
            color-scheme: light only;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff !important;
            font-family: 'Jost', Helvetica, Arial, sans-serif;
            color: #1a3016;
            -webkit-font-smoothing: antialiased;
            margin: 0 !important;
            padding: 0 !important;
        }

        .arabic {
            font-family: 'Amiri', 'Traditional Arabic', 'Scheherazade New',
                'Noto Naskh Arabic', serif;
        }

        .serif {
            font-family: 'Cormorant Garamond', 'Palatino Linotype',
                Palatino, Georgia, serif;
        }

        .shell {
            background-color: #f4f8f0;
            padding: 32px 16px 48px;
        }

        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(160deg, #1a3016 0%, #2d5a20 60%, #1a3016 100%);
            border-radius: 16px 16px 0 0;
            padding: 44px 32px 40px;
            text-align: center;
        }

        .bismillah {
            font-size: 28px;
            color: #7fd468;
            letter-spacing: 1px;
            margin-bottom: 20px;
            direction: rtl;
            line-height: 1.6;
        }

        .amana-title {
            font-size: 38px;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .amana-title span {
            color: #7fd468;
        }

        .amana-subtitle {
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #7fd468;
            font-weight: 500;
            padding: 0 16px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .mission-badge {
            display: inline-block;
            background: rgba(127, 212, 104, 0.15);
            border: 1px solid rgba(127, 212, 104, 0.4);
            border-radius: 40px;
            padding: 6px 20px;
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #a8e895;
            font-weight: 500;
            margin-bottom: 14px;
        }

        .mission-title {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .mission-subtitle {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 300;
            margin-top: 6px;
        }

        /* ── Stripe ── */
        .stripe {
            height: 5px;
            background: repeating-linear-gradient(90deg,
                    #2d8022 0px, #2d8022 8px,
                    #3a9e2e 8px, #3a9e2e 10px,
                    #4ab83e 10px, #4ab83e 12px,
                    #3a9e2e 12px, #3a9e2e 14px,
                    #2d8022 14px, #2d8022 22px,
                    #ffffff 22px, #ffffff 28px);
        }

        /* ── Body ── */
        .body {
            background-color: #ffffff;
            border-left: 1px solid #cce8b0;
            border-right: 1px solid #cce8b0;
            padding: 40px 32px;
        }

        .greeting {
            font-size: 22px;
            font-weight: 600;
            color: #1a3016;
            margin-bottom: 20px;
            line-height: 1.35;
        }

        .greeting em {
            color: #3cb832;
            font-style: italic;
        }

        .body-text {
            font-size: 15px;
            line-height: 1.85;
            color: #2e4a2a;
            font-weight: 300;
            margin-bottom: 18px;
            text-align: justify;
        }

        .body-text strong {
            color: #1a6e14;
            font-weight: 500;
        }

        /* ── CTA button ── */
        .cta-wrap {
            text-align: center;
            margin: 32px 0;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #2d8022 0%, #3cb832 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
            padding: 16px 36px;
            border-radius: 50px;
            box-shadow: 0 4px 20px rgba(45, 128, 34, 0.35);
        }

        .cta-note {
            font-size: 12px;
            color: #7a9e6e;
            margin-top: 12px;
            font-weight: 300;
        }

        /* ── What you can do card ── */
        .features-card {
            background: linear-gradient(135deg, #1a3016 0%, #2d5a20 100%);
            border-radius: 12px;
            padding: 24px 28px;
            margin: 28px 0;
        }

        .features-card-label {
            font-size: 10px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #7fd468;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .feature-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .feature-row:last-child {
            margin-bottom: 0;
        }

        .feature-icon {
            font-size: 20px;
            width: 28px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .feature-text {
            font-size: 14px;
            color: #e0f2cc;
            font-weight: 300;
            line-height: 1.5;
        }

        .feature-text strong {
            color: #a8e895;
            font-weight: 600;
        }

        /* ── Expiry warning ── */
        .expiry-box {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin: 24px 0;
        }

        .expiry-icon {
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .expiry-text {
            font-size: 13px;
            color: #7d5800;
            font-weight: 300;
            line-height: 1.6;
        }

        .expiry-text strong {
            color: #5c3d00;
            font-weight: 600;
        }

        /* ── Closing ── */
        .closing-text {
            font-size: 15px;
            line-height: 1.85;
            color: #2e4a2a;
            font-weight: 300;
            margin-top: 28px;
            margin-bottom: 28px;
            text-align: center;
            font-style: italic;
        }

        .closing-text strong {
            color: #1a6e14;
            font-weight: 600;
            font-style: normal;
        }

        .jazakum {
            text-align: center;
            margin-bottom: 8px;
        }

        .jazakum-arabic {
            font-size: 36px;
            font-weight: 700;
            color: #2d8022;
            direction: rtl;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .footer {
            background: linear-gradient(160deg, #eaf6dd, #e0f2cc);
            border: 1px solid #c0df98;
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 28px 32px;
            text-align: center;
        }

        .footer-logo {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #3cb832;
            margin-bottom: 12px;
        }

        .footer p {
            font-size: 12px;
            color: #3a7830;
            line-height: 1.9;
            font-weight: 300;
        }

        .footer a {
            color: #2d8022;
            text-decoration: none;
        }

        /* ── Mobile ── */
        @media only screen and (max-width: 480px) {
            .shell {
                padding: 0;
            }

            .bismillah {
                font-size: 22px;
            }

            .header {
                padding: 36px 20px 32px;
                border-radius: 0;
            }

            .amana-title {
                font-size: 30px;
                letter-spacing: 4px;
            }

            .mission-title {
                font-size: 22px;
            }

            .body {
                padding: 28px 20px;
                border-left: none;
                border-right: none;
            }

            .greeting {
                font-size: 20px;
            }

            .features-card {
                padding: 20px;
            }

            .footer {
                padding: 24px 20px;
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

            {{-- ── Header ── --}}
            <div class="header">
                <p class="bismillah arabic">بسم الله الرحمن الرحيم</p>

                <div class="amana-title serif">AM<span>A</span>NA</div>
                <div class="amana-subtitle">
                    Association Musulmane de l'Agglomération Nantaise et ses Alentours
                </div>

                {{-- Divider --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="margin-bottom:24px;">
                    <tr>
                        <td width="43%" style="border-top:1px solid rgba(127,212,104,0.4); vertical-align:middle;"></td>
                        <td
                            style="text-align:center; padding:0 5px; white-space:nowrap; color:#7fd468; font-size:30px;">
                            &#10022;</td>
                        <td width="43%" style="border-top:1px solid rgba(127,212,104,0.4); vertical-align:middle;"></td>
                    </tr>
                </table>

                <div class="mission-badge">Candidature validée</div>
                <div class="mission-title">Bienvenue parmi nous&nbsp;!</div>
                <div class="mission-subtitle">AMANA Planning</div>
            </div>

            {{-- ── Stripe ── --}}
            <div class="stripe"></div>

            {{-- ── Body ── --}}
            <div class="body">

                <p class="greeting serif">
                    Cher <em>{{ $prenom }}</em>,
                </p>

                <p class="body-text">
                    Nous avons le plaisir de vous informer que votre candidature bénévole chez
                    <strong>AMANA</strong> a été <strong>validée</strong> par l'équipe d'administration.
                    Bienvenue dans notre équipe&nbsp;!
                </p>

                <p class="body-text">
                    Pour accéder à l'application et consulter votre planning, vous devez d'abord
                    <strong>créer votre mot de passe</strong> en cliquant sur le bouton ci-dessous.
                </p>

                {{-- CTA --}}
                <div class="cta-wrap">
                    <a href="{{ $resetUrl }}" class="cta-button">
                        🔐 &nbsp; Créer mon mot de passe
                    </a>
                    <p class="cta-note">Ce lien est valable 60 minutes.</p>
                </div>

                {{-- Expiry warning --}}
                <div class="expiry-box">
                    <span class="expiry-icon">⚠️</span>
                    <div class="expiry-text">
                        Si le lien a expiré, rendez-vous sur la page de connexion et utilisez
                        <strong>« Mot de passe oublié »</strong> pour en obtenir un nouveau,
                        ou contactez un administrateur.
                    </div>
                </div>

                {{-- Features card --}}
                <div class="features-card">
                    <div class="features-card-label">&#10022; &nbsp; Une fois connecté·e, vous pourrez</div>
                    <div class="feature-row">
                        <span class="feature-icon">📅</span>
                        <div class="feature-text">
                            <strong>Consulter le planning</strong> des permanences vendredis &amp; samedis
                        </div>
                    </div>
                    <div class="feature-row">
                        <span class="feature-icon">📄</span>
                        <div class="feature-text">
                            <strong>Télécharger le planning</strong> au format PDF
                        </div>
                    </div>
                    <div class="feature-row">
                        <span class="feature-icon">🏖️</span>
                        <div class="feature-text">
                            <strong>Déclarer vos absences</strong> pour que le planning soit ajusté
                        </div>
                    </div>
                    <div class="feature-row">
                        <span class="feature-icon">🔒</span>
                        <div class="feature-text">
                            <strong>Gérer vos disponibilités</strong> par tâche et par jour
                        </div>
                    </div>
                </div>

                {{-- Closing divider --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="margin:36px 0 28px;">
                    <tr>
                        <td style="border-top:1px solid #cce8b0; vertical-align:middle;">&nbsp;</td>
                        <td
                            style="text-align:center; padding:0 16px; white-space:nowrap; color:#3a9e2e; font-size:15px;">
                            &#10022;</td>
                        <td style="border-top:1px solid #cce8b0; vertical-align:middle;">&nbsp;</td>
                    </tr>
                </table>

                <p class="closing-text">
                    Nous comptons sur votre <strong>engagement</strong> et votre <strong>générosité</strong><br>
                    pour que notre mission collective soit une réussite.
                </p>

                <div class="jazakum">
                    <p class="jazakum-arabic arabic">جزاكم الله خيرا</p>
                </div>

            </div>

            {{-- ── Footer ── --}}
            <div class="footer">
                <div class="footer-logo serif">AMANA</div>
                <div style="width:36px; height:1px; background:#bfdf98; margin:0 auto 12px;"></div>
                <p>
                    Vous recevez cet email suite à la validation de votre candidature bénévole.<br />
                    Pour toute question&nbsp;:
                    <a href="mailto:amana44.benevole@gmail.com">amana44.benevole@gmail.com</a>
                </p>
            </div>

        </div>
    </div>
</body>

</html>