{{-- resources/views/emails/echanges/accepte.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Échange confirmé — AMANA</title>
    @include('emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">

        @include('emails.partials._header', [
            'badge'    => 'Échange confirmé',
            'title'    => 'Votre échange de créneau est effectif&nbsp;!',
            'titleSub' => 'AMANA Planning',
        ])

        <div class="stripe"></div>

        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>

            @if($role === 'demandeur')
                <p class="body-text">
                    Bonne nouvelle — <strong>{{ $echange->cible->prenom }} {{ $echange->cible->nom }}</strong>
                    a accepté votre demande d'échange. Voici ce qui a changé dans votre planning&nbsp;:
                </p>
            @else
                <p class="body-text">
                    Vous avez accepté la demande d'échange de
                    <strong>{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>.
                    Voici ce qui a changé dans votre planning&nbsp;:
                </p>
            @endif

            {{-- What changed --}}
            <div style="background:#0c1e2e;border-radius:10px;padding:22px 26px;margin:22px 0;">
                <div style="font-size:9.5px;letter-spacing:2.5px;text-transform:uppercase;color:#7dd3fc;font-weight:600;margin-bottom:16px;">
                    ✦ &nbsp; Récapitulatif de l'échange
                </div>

                @php
                    // From this person's perspective: what they had → what they now have
                    if ($role === 'demandeur') {
                        $avait        = ['creneau' => $echange->creneauDemandeur, 'tache' => $echange->tacheDemandeur];
                        $maintenant   = ['creneau' => $echange->creneauCible,     'tache' => $echange->tacheCible];
                        $autrePersonne = $echange->cible;
                    } else {
                        $avait        = ['creneau' => $echange->creneauCible,     'tache' => $echange->tacheCible];
                        $maintenant   = ['creneau' => $echange->creneauDemandeur, 'tache' => $echange->tacheDemandeur];
                        $autrePersonne = $echange->demandeur;
                    }
                @endphp

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                    <tr>
                        <td style="width:45%;vertical-align:top;background:rgba(255,255,255,0.05);border-radius:8px;padding:14px 16px;opacity:0.65;">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);margin-bottom:8px;">
                                Vous aviez
                            </div>
                            <div style="font-size:16px;font-weight:700;color:#ffffff;margin-bottom:4px;text-decoration:line-through;opacity:0.7;">
                                {{ $avait['creneau']->date->locale('fr')->isoFormat('ddd D MMM') }}
                            </div>
                            <div style="font-size:12px;color:#bae6fd;">{{ $avait['tache']->libelle }}</div>
                        </td>
                        <td style="width:10%;text-align:center;vertical-align:middle;font-size:20px;color:#059669;padding:0 8px;">→</td>
                        <td style="width:45%;vertical-align:top;background:rgba(5,150,105,0.15);border:1px solid rgba(5,150,105,0.4);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.4);margin-bottom:8px;">
                                Vous avez maintenant
                            </div>
                            <div style="font-size:16px;font-weight:700;color:#ffffff;margin-bottom:4px;">
                                {{ $maintenant['creneau']->date->locale('fr')->isoFormat('ddd D MMM') }}
                            </div>
                            <div style="font-size:12px;color:#6ee7b7;font-weight:600;">{{ $maintenant['tache']->libelle }}</div>
                        </td>
                    </tr>
                </table>

                <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.08);font-size:11.5px;color:rgba(255,255,255,0.35);">
                    Échange effectué avec <strong style="color:rgba(255,255,255,0.55);">{{ $autrePersonne->prenom }} {{ $autrePersonne->nom }}</strong>
                    @if($echange->approuve_par)
                        · approuvé par un administrateur
                    @endif
                </div>
            </div>

            <div class="cta-wrap">
                <a href="{{ $urlPlanning }}" class="cta-button">📅 &nbsp; Voir mon planning</a>
            </div>

            @include('emails.partials._closing')
        </div>

        @include('emails.partials._footer')
    </div>
</div>
</body>
</html>
