{{-- resources/views/settings/partials/_offset-row.blade.php --}}
{{--
    Paramètres : $codeTache (string), $groupe (array), $horaires (array)
    Rendu d'une ligne de la table des décalages (desktop).
--}}
@php
    $isSandwich = $codeTache === 'rappel_sandwich';
    $heureCours = $horaires['heure_cours']['valeur_raw'] ?? '20:00';
    [$hh, $mm]  = explode(':', $heureCours);
    $baseMin    = (int)$hh * 60 + (int)$mm;
    $dOff       = (int)($groupe['debut']['valeur_raw'] ?? 0);
    $fOff       = (int)($groupe['fin']['valeur_raw'] ?? 60);
    $calcDebut  = sprintf('%02d:%02d', intdiv(((($baseMin + $dOff) % 1440) + 1440) % 1440, 60), ((($baseMin + $dOff) % 1440) + 1440) % 1440 % 60);
    $calcFin    = sprintf('%02d:%02d', intdiv(((($baseMin + $fOff) % 1440) + 1440) % 1440, 60), ((($baseMin + $fOff) % 1440) + 1440) % 1440 % 60);
@endphp
<tr class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors">

    {{-- Tâche --}}
    @php $description = $groupe['debut']['description'] ?? $groupe['fin']['description'] ?? null; @endphp
    <td class="px-5 py-3 align-middle">
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold chip-{{ $codeTache }}">
            {{ $groupe['libelle'] }}
        </span>
        @if($isSandwich)
            <div class="text-[11px] text-ink-muted mt-1">Horaire fixe — valeurs ignorées</div>
        @endif
        @if($description)
            <div class="text-[11.5px] text-ink-muted mt-1 leading-snug max-w-[260px]">{{ $description }}</div>
        @endif
    </td>

    {{-- Début --}}
    <td class="px-4 py-3 text-center align-middle">
        @if($groupe['debut'])
            <div class="flex flex-col items-center gap-0.5">
                <input type="number"
                    name="settings[{{ $groupe['debut']['cle'] }}]"
                    value="{{ $groupe['debut']['valeur_raw'] }}"
                    step="1" min="-999" max="999"
                    {{ $isSandwich ? 'disabled' : '' }}
                    class="w-[90px] px-2.5 py-1.5 border-[1.5px] border-ink-faint rounded-lg text-base text-center font-body text-ink bg-surface-2 outline-none transition
                        focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                        disabled:opacity-50 disabled:cursor-not-allowed">
                @if(!$isSandwich)
                    <span class="text-[11px] text-ink-muted">min</span>
                @endif
            </div>
        @else
            <span class="text-ink-faint">—</span>
        @endif
    </td>

    {{-- Fin --}}
    <td class="px-4 py-3 text-center align-middle">
        @if($groupe['fin'])
            <div class="flex flex-col items-center gap-0.5">
                <input type="number"
                    name="settings[{{ $groupe['fin']['cle'] }}]"
                    value="{{ $groupe['fin']['valeur_raw'] }}"
                    step="1" min="-999" max="999"
                    {{ $isSandwich ? 'disabled' : '' }}
                    class="w-[90px] px-2.5 py-1.5 border-[1.5px] border-ink-faint rounded-lg text-base text-center font-body text-ink bg-surface-2 outline-none transition
                        focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]
                        disabled:opacity-50 disabled:cursor-not-allowed">
                @if(!$isSandwich)
                    <span class="text-[11px] text-ink-muted">min</span>
                @endif
            </div>
        @else
            <span class="text-ink-faint">—</span>
        @endif
    </td>

    {{-- Horaire calculé --}}
    <td class="px-5 py-3 align-middle">
        @if($isSandwich)
            <span class="text-[12.5px] text-ink-muted font-semibold">08:00 → 08:15</span>
        @else
            <span class="horaire-preview text-[12.5px] text-accent font-semibold"
                data-base="{{ $heureCours }}"
                data-debut-input="settings[{{ $groupe['debut']['cle'] ?? '' }}]"
                data-fin-input="settings[{{ $groupe['fin']['cle'] ?? '' }}]">
                {{ $calcDebut }} → {{ $calcFin }}
            </span>
        @endif
    </td>

</tr>
