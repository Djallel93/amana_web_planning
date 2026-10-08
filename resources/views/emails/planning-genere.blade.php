{{-- resources/views/emails/planning-genere.blade.php --}}
{{--
Email « Planning généré / régénéré » — voir App\Notifications\PlanningGenereNotification.
Habillage : partials partagés d'amana_shared (même principe que rappel-creneau).

Variables :
  $prenom, $titre, $logoCid
  $manuel        : bool — génération manuelle (true) ou régénération automatique (false)
  $detail        : string|null — « suite à … » d'une régénération automatique
  $acteur        : string|null — qui l'a déclenchée (null : aucun utilisateur connecté)
  $periode, $quand : texte déjà formaté
  $joursGeneres, $nonAssignes : int
  $urlPlanning   : lien du bouton

Tout est affiché échappé ({{ }}) : $detail et $acteur contiennent des noms saisis par des utilisateurs.
--}}
<!DOCTYPE html>
<html lang="fr" dir="ltr">

<head>
    <title>{{ $titre }} — AMANA Planning</title>
    @include('amana-shared::emails.partials._head')
</head>

<body>
    <div class="shell">
        <div class="wrapper">

            @include('amana-shared::emails.partials._header', [
                'badge' => 'Administration',
                'title' => e($titre),
                'titleSub' => 'AMANA Planning',
            ])

            <div class="stripe"></div>

            <div class="body">

                <p class="greeting">Cher(e) <em>{{ $prenom }}</em>,</p>

                <p class="body-text">
                    @if($manuel)
                        Le planning vient d'être <strong>généré</strong> depuis la page Planning &rsaquo; Générer.
                    @else
                        Le planning a été <strong>régénéré automatiquement</strong>
                        @if(!empty($detail))
                            suite à {{ $detail }}.
                        @else
                            pour tenir compte d'une modification.
                        @endif
                    @endif
                </p>

                <table class="info-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td class="info-icon">📅</td>
                        <td class="info-content">
                            <div class="info-title">Période (re)générée</div>
                            <div class="info-text">{{ ucfirst($periode) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td class="info-icon">📊</td>
                        <td class="info-content">
                            <div class="info-title" style="margin-top:12px;">Résultat</div>
                            <div class="info-text">
                                {{ $joursGeneres }} jour{{ $joursGeneres > 1 ? 's' : '' }} généré{{ $joursGeneres > 1 ? 's' : '' }}
                                &nbsp;·&nbsp;
                                {{ $nonAssignes }} tâche{{ $nonAssignes > 1 ? 's' : '' }} non assignée{{ $nonAssignes > 1 ? 's' : '' }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="info-icon">👤</td>
                        <td class="info-content">
                            <div class="info-title" style="margin-top:12px;">Déclenché par</div>
                            <div class="info-text">
                                {{ $acteur ?? 'Action automatique' }} &nbsp;·&nbsp; {{ $quand }}
                            </div>
                        </td>
                    </tr>
                </table>

                @if($nonAssignes > 0)
                    <table class="warn-box" role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td class="warn-icon">⚠️</td>
                            <td class="warn-text">
                                <strong>{{ $nonAssignes }} tâche{{ $nonAssignes > 1 ? 's' : '' }} sans personne assignée.</strong><br>
                                Il peut s'agir d'un événement bloquant ou d'un manque de personnes disponibles
                                (absences, restrictions). Vérifiez le planning et complétez-le si besoin.
                            </td>
                        </tr>
                    </table>
                @endif

                <div class="cta-wrap">
                    <a href="{{ $urlPlanning }}" class="cta-button">📅 &nbsp; Voir le planning</a>
                </div>

            </div>

            @include('amana-shared::emails.partials._footer', [
                'footerText' => "Vous recevez cet email en tant qu'administrateur ou gestionnaire d'AMANA Planning.",
            ])

        </div>
    </div>
</body>

</html>
