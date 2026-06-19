{{-- resources/views/emails/echanges/expire.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Échange expiré — AMANA</title>
    @include('emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">
        @include('emails.partials._header', [
            'badge'    => 'Échange expiré',
            'title'    => 'Votre demande d\'échange a expiré',
            'titleSub' => 'AMANA Planning',
        ])
        <div class="stripe"></div>
        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>
            <p class="body-text">
                Votre demande d'échange avec
                <strong>{{ $echange->cible->prenom }} {{ $echange->cible->nom }}</strong>
                n'a pas reçu de réponse avant la date du créneau
                (<strong>{{ $echange->creneauDemandeur->date->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>).
                La demande est maintenant expirée et votre assignation reste inchangée.
            </p>
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
