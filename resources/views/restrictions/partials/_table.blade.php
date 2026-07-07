{{-- resources/views/restrictions/partials/_table.blade.php --}}
{{--
    Paramètres attendus :
      $editable       bool   — true = inputs éditables, false = disabled
      $personnes      Collection
      $taches         Collection
      $restrictionsMap array
      $user           Personne connectée
--}}
<table class="w-full border-collapse text-[13.5px]" style="min-width:680px;">
    <thead>
        <tr>
            <th rowspan="2"
                class="align-middle text-left pl-5 pr-3 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body whitespace-nowrap"
                style="min-width:160px;">
                Personne
            </th>
            @foreach(['Vendredi', 'Samedi'] as $jour)
                <th colspan="{{ $taches->count() }}"
                    class="text-center py-2.5 px-2 bg-accent text-white text-xs font-semibold border-b border-surface-3
                           {{ $loop->last ? 'jour-separator' : '' }}">
                    {{ $jour }}
                </th>
            @endforeach
        </tr>
        <tr>
            @foreach(['Vendredi', 'Samedi'] as $jour)
                @foreach($taches as $tache)
                    <th class="text-center py-1.5 px-1 text-[10.5px] font-bold bg-surface-2 border-b border-surface-3 whitespace-nowrap tracking-[0.3px]
                               sub-{{ $tache->code }}
                               {{ ($jour === 'Samedi' && $loop->first) ? 'jour-separator' : '' }}">
                        {{ $tache->libelle }}
                    </th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($personnes as $personne)
            @php $isMe = $personne->id === $user->id; @endphp
            <tr class="{{ $isMe ? 'my-row' : 'hover:bg-surface-2' }} transition-colors">
                <td class="text-left pl-5 pr-3 py-2.5 font-semibold text-ink whitespace-nowrap border-b border-surface-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-[26px] h-[26px] bg-accent rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0">
                            {{ strtoupper(substr($personne->prenom, 0, 1)) }}
                        </div>
                        {{ $personne->prenom }} {{ $personne->nom }}
                    </div>
                </td>
                @foreach(['Vendredi', 'Samedi'] as $jour)
                    @foreach($taches as $tache)
                        @php $autorise = $restrictionsMap[$personne->id][$tache->id][$jour] ?? true; @endphp
                        <td class="text-center py-2.5 px-1.5 border-b border-surface-3
                                   {{ ($jour === 'Samedi' && $loop->first) ? 'jour-separator' : '' }}">
                            <input type="checkbox"
                                   @if($editable)
                                       name="checkboxes[{{ $personne->id }}][{{ $tache->id }}][{{ $jour }}]"
                                       value="1"
                                   @else
                                       disabled
                                   @endif
                                   {{ $autorise ? 'checked' : '' }}
                                   title="{{ $personne->prenom }} — {{ $tache->libelle }} — {{ $jour }}"
                                   class="w-4 h-4 accent-accent {{ $editable ? 'cursor-pointer' : 'cursor-not-allowed opacity-50' }}">
                        </td>
                    @endforeach
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
