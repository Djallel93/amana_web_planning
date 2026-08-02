{{-- resources/views/emails/echanges/refuse.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Échange refusé — AMANA</title>
    @include('emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">
        @include('emails.partials._header', [
            'badge'    => 'Échange refusé',
            'title'    => 'Votre demande d\'échange a été refusée',
            'titleSub' => 'AMANA Planning',
        ])
        <div class="stripe"></div>
        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>
            <p class="body-text">
                <strong>{{ $echange->cible->prenom }} {{ $echange->cible->nom }}</strong>
                n'a pas pu accepter votre demande d'échange de créneau.
                Votre créneau du
                <strong>{{ $echange->creneauDemandeur->date->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>
                ({{ $echange->tacheDemandeur->libelle }}) reste inchangé.
            </p>
            <p class="body-text">
                Vous pouvez initier une nouvelle demande avec un autre membre depuis votre planning.
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
