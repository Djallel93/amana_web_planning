{{-- resources/views/emails/echanges/demande.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Demande d'échange de créneau — AMANA</title>
    @include('emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">

        @include('emails.partials._header', [
            'badge'    => 'Échange de créneau',
            'title'    => 'Vous avez une demande d\'échange&nbsp;!',
            'titleSub' => 'AMANA Planning',
        ])

        <div class="stripe"></div>

        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>

            <p class="body-text">
                <strong>{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>
                vous propose d'échanger vos créneaux sur AMANA Planning.
            </p>

            {{-- Swap details card --}}
            <div style="background:#0c1e2e;border-radius:10px;padding:22px 26px;margin:22px 0;">
                <div style="font-size:9.5px;letter-spacing:2.5px;text-transform:uppercase;color:#7dd3fc;font-weight:600;margin-bottom:16px;">
                    ✦ &nbsp; Détails de l'échange
                </div>

                {{-- Their slot → Your slot --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    <tr>
                        {{-- A's slot (what they give up) --}}
                        <td style="width:45%;vertical-align:top;background:rgba(255,255,255,0.05);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);margin-bottom:8px;">
                                {{ $echange->demandeur->prenom }} cède
                            </div>
                            <div style="font-size:16px;font-weight:700;color:#ffffff;margin-bottom:4px;">
                                {{ $echange->creneauDemandeur->date->locale('fr')->isoFormat('ddd D MMM') }}
                            </div>
                            <div style="font-size:12px;color:#bae6fd;font-weight:600;">
                                {{ $echange->tacheDemandeur->libelle }}
                            </div>
                            <div style="font-size:11px;color:rgba(255,255,255,0.35);margin-top:4px;">
                                S{{ $echange->creneauDemandeur->semaine }}
                                · {{ $echange->creneauDemandeur->jour }}
                            </div>
                        </td>

                        {{-- Arrow --}}
                        <td style="width:10%;text-align:center;vertical-align:middle;font-size:20px;color:#0ea5e9;padding:0 8px;">
                            ⇄
                        </td>

                        {{-- B's slot (what they get) --}}
                        <td style="width:45%;vertical-align:top;background:rgba(14,165,233,0.12);border:1px solid rgba(14,165,233,0.3);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);margin-bottom:8px;">
                                Votre créneau
                            </div>
                            <div style="font-size:16px;font-weight:700;color:#ffffff;margin-bottom:4px;">
                                {{ $echange->creneauCible->date->locale('fr')->isoFormat('ddd D MMM') }}
                            </div>
                            <div style="font-size:12px;color:#bae6fd;font-weight:600;">
                                {{ $echange->tacheCible->libelle }}
                            </div>
                            <div style="font-size:11px;color:rgba(255,255,255,0.35);margin-top:4px;">
                                S{{ $echange->creneauCible->semaine }}
                                · {{ $echange->creneauCible->jour }}
                            </div>
                        </td>
                    </tr>
                </table>

                {{-- Expiry note --}}
                <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.08);font-size:11.5px;color:rgba(255,255,255,0.35);display:flex;align-items:center;gap:7px;">
                    <span>⏰</span>
                    <span>
                        Cette demande expire le
                        <strong style="color:rgba(255,255,255,0.55);">
                            {{ $echange->expires_at->locale('fr')->isoFormat('D MMMM YYYY') }}
                        </strong>
                        (date de votre créneau).
                    </span>
                </div>
            </div>

            <p class="body-text">
                Si vous acceptez, vos créneaux seront échangés immédiatement et vous serez tous les deux notifiés.
                Si vous refusez, {{ $echange->demandeur->prenom }} en sera informé.
            </p>

            {{-- CTA buttons --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px auto;text-align:center;">
                <tr>
                    <td style="padding-right:10px;">
                        <a href="{{ $urlAccept }}"
                           style="display:inline-block;background:#059669;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 28px;border-radius:8px;box-shadow:0 4px 16px rgba(5,150,105,0.35);">
                            ✅ &nbsp; Accepter l'échange
                        </a>
                    </td>
                    <td style="padding-left:10px;">
                        <a href="{{ $urlRefuse }}"
                           style="display:inline-block;background:#e11d48;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 28px;border-radius:8px;box-shadow:0 4px 16px rgba(225,29,72,0.3);">
                            ✕ &nbsp; Refuser
                        </a>
                    </td>
                </tr>
            </table>

            <table class="warn-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="warn-icon">⚠️</td>
                    <td class="warn-text">
                        Ces liens sont à usage unique. Une fois cliqué, l'action est immédiate et irréversible.
                        Si vous souhaitez modifier l'échange par la suite, vous devrez initier une nouvelle demande.
                    </td>
                </tr>
            </table>

            @include('emails.partials._closing')
        </div>

        @include('emails.partials._footer')
    </div>
</div>
</body>
</html>
