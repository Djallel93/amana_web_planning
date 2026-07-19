{{-- resources/views/emails/rappel-creneau.blade.php --}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <title>Rappel — AMANA Planning</title>
    @include('emails.partials._head')
</head>

<body>
    <div class="shell">
        <div class="wrapper">

            @include('emails.partials._header', [
                'badge' => $badge,
                'title' => 'Rappel de créneau',
                'titleSub' => $titleSub,
            ])

            <div class="stripe"></div>

            <div class="body">

                <p class="greeting">Cher <em>{{ $prenom }}</em>,</p>

                <p class="body-text">{!! $intro !!}</p>

                <table class="info-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="info-icon">📅</td>
                        <td class="info-content">
                            <div class="info-title">{{ $tache }}</div>
                            <div class="info-text">
                                {{ ucfirst($dateFormatee) }}
                                @if($heureDebut)
                                    &nbsp;·&nbsp; {{ $heureDebut }}@if($heureFin) – {{ $heureFin }}@endif
                                @endif
                            </div>
                            @if(!empty($description))
                                <div class="info-text" style="margin-top:10px; white-space: pre-line;">{{ $description }}</div>
                            @endif
                        </td>
                    </tr>
                </table>

                @include('emails.partials._hadith')

                @include('emails.partials._closing')

            </div>

            @include('emails.partials._footer')

        </div>
    </div>
</body>
</html>
