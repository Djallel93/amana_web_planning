{{-- resources/views/partials/color-select.blade.php --}}
{{--
    <select> de couleur Google Calendar + pastille de couleur affichant un
    aperçu de la couleur sélectionnée, mise à jour en direct au changement.

    Un <select> natif ne permet pas d'afficher de façon fiable une couleur
    dans chaque <option> sur tous les navigateurs — on affiche donc la
    pastille À CÔTÉ du select plutôt que dedans, mise à jour par un seul
    script partagé (@once) au changement de sélection.

    Props (via $attributes du @include) :
      - name           (string)      Nom du champ (ex: "couleur" ou "settings[couleur_entree]")
      - id             (string)      Id HTML du select
      - selected       (string|null) Valeur actuellement sélectionnée (colorId '1'-'11' ou '')
      - allowEmpty     (bool)        Si true, ajoute une option vide "Couleur par défaut…" en tête
      - emptyLabel     (string)      Libellé de l'option vide (si allowEmpty)
--}}
@php
    $allowEmpty = $allowEmpty ?? false;
    $emptyLabel = $emptyLabel ?? 'Couleur par défaut du calendrier';
    $selectedValue = (string) ($selected ?? '');
    $currentHex = \App\Helpers\GoogleCalendarColors::PALETTE[$selectedValue]['hex'] ?? 'transparent';
@endphp

<div class="flex items-center gap-2">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        data-color-select
        class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition cursor-pointer
               focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]"
    >
        @if($allowEmpty)
            <option value="" data-hex="transparent" {{ $selectedValue === '' ? 'selected' : '' }}>
                {{ $emptyLabel }}
            </option>
        @endif
        @foreach(\App\Helpers\GoogleCalendarColors::PALETTE as $colorId => $couleur)
            <option value="{{ $colorId }}" data-hex="{{ $couleur['hex'] }}" {{ $selectedValue === (string) $colorId ? 'selected' : '' }}>
                {{ $couleur['nom'] }}
            </option>
        @endforeach
    </select>
    <span
        data-color-swatch
        class="w-8 h-8 rounded-full border-[1.5px] border-surface-border flex-shrink-0"
        style="background-color: {{ $currentHex }};"
        aria-hidden="true"
    ></span>
</div>

@once
    <script>
        (function () {
            // Un seul listener délégué : couvre tous les <select data-color-select>
            // présents sur la page (formulaire événement, page Paramètres…),
            // y compris ceux ajoutés dynamiquement plus tard.
            document.addEventListener('change', function (e) {
                var select = e.target.closest && e.target.closest('[data-color-select]');
                if (!select) return;

                var swatch = select.parentElement
                    ? select.parentElement.querySelector('[data-color-swatch]')
                    : null;
                if (!swatch) return;

                var option = select.options[select.selectedIndex];
                var hex = option ? option.getAttribute('data-hex') : null;
                swatch.style.backgroundColor = hex || 'transparent';
            });
        })();
    </script>
@endonce
