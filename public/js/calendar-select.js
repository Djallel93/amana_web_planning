// public/js/calendar-select.js
// Composant dropdown de sélection de calendrier avec recherche intégrée.
// Utilisé dans evenements/form et settings/index.
// Sans dépendances npm — JS pur, compatible IONOS shared hosting.
//
// Usage :
//   CalendarSelect.init({
//     inputId      : 'calendar_name',   // id du <input type="hidden"> qui reçoit la valeur
//     triggerId    : 'calendar_trigger',// id du bouton/div déclencheur
//     apiUrl       : '/api/calendriers',// URL de l'endpoint Laravel
//     currentValue : 'AMANA - Planning',// valeur pré-sélectionnée (peut être vide)
//   });

const CalendarSelect = (function () {

    // ── Styles injectés une seule fois ──────────────────────────────────────
    let stylesInjected = false;

    function injectStyles() {
        if (stylesInjected) return;
        stylesInjected = true;

        const css = `
        .cs-wrapper {
            position: relative;
            width: 100%;
        }
        .cs-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--ink-faint, #d1d5db);
            border-radius: var(--radius, 8px);
            background: var(--surface, #fff);
            color: var(--ink, #111);
            font-size: 13.5px;
            font-family: inherit;
            cursor: pointer;
            text-align: left;
            transition: border-color 0.15s;
            min-height: 40px;
        }
        .cs-trigger:hover,
        .cs-trigger.open {
            border-color: var(--app-accent, #6366f1);
        }
        .cs-trigger.open {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        .cs-trigger-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cs-trigger-text.placeholder {
            color: var(--ink-muted, #9ca3af);
        }
        .cs-trigger-arrow {
            flex-shrink: 0;
            font-size: 10px;
            color: var(--ink-muted, #9ca3af);
            transition: transform 0.15s;
        }
        .cs-trigger.open .cs-trigger-arrow {
            transform: rotate(180deg);
        }
        .cs-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 9999;
            background: var(--surface, #fff);
            border: 1.5px solid var(--ink-faint, #d1d5db);
            border-radius: var(--radius, 8px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            display: none;
            overflow: hidden;
        }
        .cs-dropdown.open {
            display: block;
        }
        .cs-search-wrap {
            padding: 10px 10px 6px;
            border-bottom: 1px solid var(--surface-3, #f1f5f9);
        }
        .cs-search {
            width: 100%;
            padding: 7px 10px;
            border: 1.5px solid var(--ink-faint, #d1d5db);
            border-radius: var(--radius, 8px);
            font-size: 13px;
            font-family: inherit;
            color: var(--ink, #111);
            background: var(--surface-2, #f8fafc);
            outline: none;
            transition: border-color 0.15s;
        }
        .cs-search:focus {
            border-color: var(--app-accent, #6366f1);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
        }
        .cs-list {
            max-height: 240px;
            overflow-y: auto;
            padding: 4px 0;
        }
        .cs-item {
            padding: 9px 14px;
            font-size: 13.5px;
            color: var(--ink, #111);
            cursor: pointer;
            transition: background 0.1s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cs-item:hover {
            background: var(--surface-2, #f8fafc);
        }
        .cs-item.selected {
            background: var(--sky-bg, #e0f2fe);
            color: var(--app-accent, #6366f1);
            font-weight: 600;
        }
        .cs-item.highlighted {
            background: var(--surface-2, #f8fafc);
        }
        .cs-empty {
            padding: 14px;
            font-size: 13px;
            color: var(--ink-muted, #9ca3af);
            text-align: center;
        }
        .cs-loading {
            padding: 14px;
            font-size: 13px;
            color: var(--ink-muted, #9ca3af);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .cs-error {
            padding: 12px 14px;
            font-size: 12.5px;
            color: var(--rose, #e11d48);
            background: var(--rose-bg, #fff1f2);
            border-top: 1px solid var(--rose-border, #fecdd3);
        }
        .cs-clear-btn {
            padding: 6px 14px 8px;
            border-top: 1px solid var(--surface-3, #f1f5f9);
        }
        .cs-clear-btn button {
            font-size: 12px;
            color: var(--ink-muted, #9ca3af);
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px 0;
            font-family: inherit;
            text-decoration: underline;
        }
        .cs-clear-btn button:hover {
            color: var(--rose, #e11d48);
        }
        `;

        const style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ── Cache partagé entre toutes les instances sur la page ────────────────
    let cachedCalendars = null;
    let fetchPromise = null;

    function fetchCalendars(apiUrl) {
        if (cachedCalendars !== null) {
            return Promise.resolve(cachedCalendars);
        }
        if (fetchPromise) {
            return fetchPromise;
        }
        fetchPromise = fetch(apiUrl, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
            .then(r => r.json())
            .then(data => {
                cachedCalendars = data.calendars || [];
                return cachedCalendars;
            })
            .catch(() => {
                fetchPromise = null; // Permettre un retry
                return [];
            });
        return fetchPromise;
    }

    // ── Constructeur d'instance ─────────────────────────────────────────────
    function init(options) {
        const {
            inputId,
            triggerId,
            apiUrl,
            currentValue = '',
        } = options;

        injectStyles();

        const hiddenInput = document.getElementById(inputId);
        const trigger = document.getElementById(triggerId);

        if (!hiddenInput || !trigger) {
            console.warn('[CalendarSelect] Éléments introuvables :', inputId, triggerId);
            return;
        }

        // Construire le DOM du dropdown
        const wrapper = trigger.parentElement;
        wrapper.classList.add('cs-wrapper');

        // Label du bouton déclencheur
        const triggerText = trigger.querySelector('.cs-trigger-text');

        function setTriggerLabel(val) {
            if (val) {
                triggerText.textContent = val;
                triggerText.classList.remove('placeholder');
            } else {
                triggerText.textContent = 'Sélectionner un calendrier…';
                triggerText.classList.add('placeholder');
            }
        }

        setTriggerLabel(currentValue || hiddenInput.value);

        // Créer le dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'cs-dropdown';
        dropdown.innerHTML = `
            <div class="cs-search-wrap">
                <input class="cs-search" type="text" placeholder="Rechercher un calendrier…" autocomplete="off">
            </div>
            <div class="cs-list">
                <div class="cs-loading">⏳ Chargement…</div>
            </div>
            <div class="cs-clear-btn">
                <button type="button">✕ Effacer la sélection</button>
            </div>
        `;
        wrapper.appendChild(dropdown);

        const searchInput = dropdown.querySelector('.cs-search');
        const list = dropdown.querySelector('.cs-list');
        const clearBtn = dropdown.querySelector('.cs-clear-btn button');

        // ── Ouvrir / fermer ────────────────────────────────────────────────
        let isOpen = false;

        function open() {
            if (isOpen) return;
            isOpen = true;
            trigger.classList.add('open');
            dropdown.classList.add('open');
            searchInput.value = '';
            searchInput.focus();
            loadList('');
        }

        function close() {
            if (!isOpen) return;
            isOpen = false;
            trigger.classList.remove('open');
            dropdown.classList.remove('open');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            isOpen ? close() : open();
        });

        // Fermer si clic ailleurs
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) close();
        });

        // Fermer sur Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });

        // ── Charger et filtrer la liste ────────────────────────────────────
        function loadList(query) {
            fetchCalendars(apiUrl).then(function (calendars) {
                renderList(calendars, query);
            });
        }

        function renderList(calendars, query) {
            const q = (query || '').toLowerCase().trim();
            const filtered = q
                ? calendars.filter(c => c.toLowerCase().includes(q))
                : calendars;

            if (calendars.length === 0) {
                list.innerHTML = '<div class="cs-empty">⚠️ Aucun calendrier disponible<br><span style="font-size:11.5px;">Vérifiez la configuration Make.com</span></div>';
                return;
            }

            if (filtered.length === 0) {
                list.innerHTML = '<div class="cs-empty">Aucun résultat pour « ' + escHtml(query) + ' »</div>';
                return;
            }

            const currentVal = hiddenInput.value;
            list.innerHTML = filtered.map(function (cal) {
                const sel = cal === currentVal ? ' selected' : '';
                return '<div class="cs-item' + sel + '" data-value="' + escHtml(cal) + '">' + escHtml(cal) + '</div>';
            }).join('');

            // Clic sur un item
            list.querySelectorAll('.cs-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    const val = item.dataset.value;
                    hiddenInput.value = val;
                    setTriggerLabel(val);
                    close();
                });
            });
        }

        searchInput.addEventListener('input', function () {
            loadList(searchInput.value);
        });

        clearBtn.addEventListener('click', function () {
            hiddenInput.value = '';
            setTriggerLabel('');
            close();
        });

        // ── Pré-charger en arrière-plan dès l'init ─────────────────────────
        fetchCalendars(apiUrl);
    }

    // ── Escape HTML basique ─────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    return { init };
})();