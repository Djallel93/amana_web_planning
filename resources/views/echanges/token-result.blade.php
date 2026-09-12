{{-- resources/views/echanges/token-result.blade.php --}}
{{-- Page publique affichée après clic sur le lien accept/refuse dans l'email --}}
{{-- Pas de layout app (pas forcément connecté) --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.favicon')
    <title>
        @if($success && $action === 'accepte') Échange confirmé
        @elseif($success && $action === 'refuse') Échange refusé
        @else Lien invalide
        @endif
        — AMANA Planning
    </title>
        @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-surface-2 font-body antialiased flex items-center justify-center p-6">

    <div class="bg-surface rounded-2xl shadow-lg border border-surface-border p-10 sm:p-12 max-w-md w-full text-center">

        {{-- Brand --}}
        <div class="flex items-center justify-center gap-2.5 mb-8">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="w-9 h-9 rounded-lg object-cover">
            <span class="font-heading text-[16px] font-semibold text-ink">AMANA Planning</span>
        </div>

        @if($success && $action === 'accepte')
            <div class="text-[56px] mb-5">✅</div>
            <h1 class="font-heading text-[22px] font-bold text-ink mb-3 tracking-tight">Échange confirmé&nbsp;!</h1>
            <p class="text-[14.5px] text-ink-muted leading-relaxed mb-6">
                L'échange a bien été effectué. Les deux membres ont été notifiés par email
                et leurs plannings ont été mis à jour.
            </p>
            @if(isset($echange))
                <div class="bg-surface-2 border border-surface-border rounded-xl p-4 mb-7 text-left">
                    <div class="flex items-start gap-3 py-2.5 border-b border-surface-3">
                        <span class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted w-20 flex-shrink-0 pt-0.5">Créneau A</span>
                        <span class="text-[13.5px] text-ink-light">
                            {{ $echange->creneauDemandeur->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            · {{ $echange->tacheDemandeur->libelle }}
                        </span>
                    </div>
                    <div class="flex items-start gap-3 py-2.5">
                        <span class="text-[10.5px] font-bold uppercase tracking-[0.6px] text-ink-muted w-20 flex-shrink-0 pt-0.5">Créneau B</span>
                        <span class="text-[13.5px] text-ink-light">
                            {{ $echange->creneauCible->date->locale('fr')->isoFormat('ddd D MMM YYYY') }}
                            · {{ $echange->tacheCible->libelle }}
                        </span>
                    </div>
                </div>
            @endif

        @elseif($success && $action === 'refuse')
            <div class="text-[56px] mb-5">✕</div>
            <h1 class="font-heading text-[22px] font-bold text-ink mb-3 tracking-tight">Échange refusé</h1>
            <p class="text-[14.5px] text-ink-muted leading-relaxed mb-7">
                Vous avez refusé la demande d'échange.
                @if(isset($echange))
                    <strong class="text-ink-light">{{ $echange->demandeur->prenom }} {{ $echange->demandeur->nom }}</strong>
                    a été notifié.
                @endif
                Votre planning reste inchangé.
            </p>

        @else
            <div class="text-[56px] mb-5">⚠️</div>
            <h1 class="font-heading text-[22px] font-bold text-ink mb-3 tracking-tight">Lien invalide</h1>
            <p class="text-[14.5px] text-ink-muted leading-relaxed mb-7">
                {{ $message ?? 'Ce lien est invalide, a déjà été utilisé, ou a expiré.' }}
            </p>
        @endif

        <a href="{{ $urlLogin }}"
           class="inline-flex items-center justify-center gap-2 min-h-[48px] px-7 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-[14px] rounded-xl
                  shadow-[0_4px_16px_rgba(3,105,161,0.3)] hover:-translate-y-px active:translate-y-0 transition-all no-underline w-full">
            🔐 Accéder à mon planning
        </a>
    </div>

</body>
</html>
