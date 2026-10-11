{{-- resources/views/emails/echanges/invalide.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <title>Demande d'échange annulée — AMANA</title>
    @include('amana-shared::emails.partials._head')
</head>
<body>
<div class="shell">
    <div class="wrapper">
        @include('amana-shared::emails.partials._header', [
            'badge'    => 'Échange annulé',
            'title'    => 'Une demande d\'échange n\'est plus valable',
            'titleSub' => 'AMANA Planning',
        ])
        <div class="stripe"></div>
        <div class="body">
            <p class="greeting">Bonjour <em>{{ $notifiable->prenom }}</em>,</p>
            <p class="body-text">
                La demande d'échange entre
                <strong>{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>
                ({{ $echange->tacheDemandeur->libelle }} du
                <strong>{{ $echange->date_demandeur?->locale('fr')->isoFormat('D MMMM YYYY') ?? '—' }}</strong>)
                et
                <strong>{{ $echange->cible->prenom }} {{ $echange->cible->nom }}</strong>
                ({{ $echange->tacheCible->libelle }} du
                <strong>{{ $echange->date_cible?->locale('fr')->isoFormat('D MMMM YYYY') ?? '—' }}</strong>)
                a été annulée automatiquement.
            </p>
            <p class="body-text">
                @if($motif === \App\Notifications\Echanges\EchangeInvalideNotification::MOTIF_PLANNING_REGENERE)
                    Le planning a été régénéré entre-temps : les créneaux concernés ont été recalculés.
                @else
                    Entre la demande et sa validation, l'affectation de l'un des deux créneaux a changé.
                @endif
                Aucun créneau n'a été échangé : votre planning actuel fait foi.
                Si l'échange reste souhaité, vous pouvez refaire une demande depuis « Mon planning ».
            </p>
            <div class="cta-wrap">
                <a href="{{ $urlPlanning }}" class="cta-button">📅 &nbsp; Voir mon planning</a>
            </div>
            @include('amana-shared::emails.partials._closing')
        </div>
        @include('amana-shared::emails.partials._footer')
    </div>
</div>
</body>
</html>
