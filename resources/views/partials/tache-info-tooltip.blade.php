{{-- resources/views/partials/tache-info-tooltip.blade.php --}}
{{--
    Icône ⓘ affichant la description d'une tâche au survol/focus.

    Usage : @include('partials.tache-info-tooltip', ['tache' => $tache])

    Tooltip en position: fixed, positionnée en JS via getBoundingClientRect().
    Nécessaire (plutôt qu'un simple tooltip CSS en position: absolute) car ce
    partial est utilisé dans des conteneurs avec overflow-x-auto / overflow-hidden
    (tableau scrollable du formulaire d'inscription) : un tooltip absolute y
    serait tronqué/coupé par le conteneur, alors qu'un tooltip fixed en
    échappe (tant qu'aucun ancêtre n'a de transform/filter/perspective).

    La bulle elle-même + son script ne sont rendus qu'une seule fois par page
    (@once) même si ce partial est inclus plusieurs fois dans une boucle
    (une fois par tâche, dans le tableau desktop ET les cartes mobile).

    N'affiche rien si la tâche n'a pas de description renseignée.
--}}
@if(!empty($tache->description))
    <button
        type="button"
        class="js-tache-tooltip-trigger inline-flex items-center justify-center w-4 h-4 rounded-full bg-surface-3 text-ink-muted text-[10px] font-bold leading-none cursor-help border-0 hover:bg-accent hover:text-white focus:bg-accent focus:text-white transition-colors outline-none align-middle"
        aria-label="Description de la tâche {{ $tache->libelle }}"
        data-tooltip="{{ $tache->description }}"
    >ⓘ</button>
@endif

@once
    <div
        id="tache-tooltip-bubble"
        class="hidden fixed z-[9999] pointer-events-none w-max max-w-[220px] px-2.5 py-1.5 rounded-md bg-gray-900 text-white text-[11.5px] leading-snug shadow-lg text-left font-normal normal-case tracking-normal"
    ></div>
    <script>
        (function () {
            var bubble = document.getElementById('tache-tooltip-bubble');
            var currentTrigger = null;

            // Sur mobile, un tap déclenche dans l'ordre : touchstart, puis
            // (événements souris synthétiques) mouseover, mousedown, focus,
            // mouseup, click. Sans garde, le mouseover synthétique ouvrait
            // déjà la bulle (currentTrigger = trigger) AVANT que le click
            // (juste après) ne la referme aussitôt en la prenant pour un
            // second tap sur un déclencheur déjà ouvert — la bulle
            // n'apparaissait donc jamais visuellement au toucher.
            // touchstart précède toujours ce mouseover synthétique : on
            // l'utilise pour désactiver définitivement les listeners
            // hover dès la première interaction tactile détectée, et
            // laisser le seul listener click piloter l'ouverture/fermeture.
            var usingTouch = false;
            document.addEventListener('touchstart', function () {
                usingTouch = true;
            }, { passive: true, once: true });

            function showTooltip(trigger) {
                var text = trigger.getAttribute('data-tooltip');
                if (!text) return;
                bubble.textContent = text;
                bubble.classList.remove('hidden');
                currentTrigger = trigger;
                positionTooltip(trigger);
            }

            function positionTooltip(trigger) {
                var rect = trigger.getBoundingClientRect();
                var bubbleRect = bubble.getBoundingClientRect();

                // Centré au-dessus du déclencheur par défaut.
                var left = rect.left + rect.width / 2 - bubbleRect.width / 2;
                var top = rect.top - bubbleRect.height - 6;

                // Pas assez de place au-dessus (ex: première ligne du tableau) → on affiche en dessous.
                if (top < 8) {
                    top = rect.bottom + 6;
                }
                // On évite que la bulle sorte de l'écran à gauche/droite.
                left = Math.max(8, Math.min(left, window.innerWidth - bubbleRect.width - 8));

                bubble.style.left = left + 'px';
                bubble.style.top = top + 'px';
            }

            function hideTooltip() {
                bubble.classList.add('hidden');
                currentTrigger = null;
            }

            document.addEventListener('mouseover', function (e) {
                if (usingTouch) return;
                var trigger = e.target.closest && e.target.closest('.js-tache-tooltip-trigger');
                if (trigger) showTooltip(trigger);
            });
            document.addEventListener('mouseout', function (e) {
                if (usingTouch) return;
                var trigger = e.target.closest && e.target.closest('.js-tache-tooltip-trigger');
                if (trigger && trigger === currentTrigger) hideTooltip();
            });
            document.addEventListener('focusin', function (e) {
                var trigger = e.target.closest && e.target.closest('.js-tache-tooltip-trigger');
                if (trigger) showTooltip(trigger);
            });
            document.addEventListener('focusout', function (e) {
                var trigger = e.target.closest && e.target.closest('.js-tache-tooltip-trigger');
                if (trigger && trigger === currentTrigger) hideTooltip();
            });
            // Tactile (mobile) : ni mouseover (pas de souris) ni focusin
            // (les navigateurs mobiles ne mettent pas systématiquement le
            // focus sur un <button> au tap) ne se déclenchent de façon
            // fiable. On bascule l'affichage au tap, et on ferme sur un tap
            // en dehors du déclencheur/de la bulle.
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest && e.target.closest('.js-tache-tooltip-trigger');

                if (trigger) {
                    if (trigger === currentTrigger) {
                        hideTooltip();
                    } else {
                        showTooltip(trigger);
                    }
                    return;
                }

                if (currentTrigger && !bubble.contains(e.target)) {
                    hideTooltip();
                }
            });
            // Le tableau scrolle horizontalement (overflow-x-auto) : on repositionne
            // la bulle si le déclencheur bouge pendant qu'elle est affichée.
            document.addEventListener('scroll', function () {
                if (currentTrigger) positionTooltip(currentTrigger);
            }, true);
        })();
    </script>
@endonce
