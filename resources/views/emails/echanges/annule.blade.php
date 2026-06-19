{{-- resources/views/emails/echanges/annule.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Demande annulée — AMANA</title>
    @include('emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">
        @include('emails.partials._header', [
            'badge'    => 'Demande annulée',
            'title'    => 'Une demande d\'échange a été annulée',
            'titleSub' => 'AMANA Planning',
        ])
        <div class="stripe"></div>
        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>
            <p class="body-text">
                <strong>{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>
                a annulé sa demande d'échange concernant votre créneau du
                <strong>{{ $echange->creneauCible->date->locale('fr')->isoFormat('D MMMM YYYY') }}</strong>
                ({{ $echange->tacheCible->libelle }}).
                Votre planning n'a pas été modifié.
            </p>
            @include('emails.partials._closing')
        </div>
        @include('emails.partials._footer')
    </div>
</div>
</body>
</html>
