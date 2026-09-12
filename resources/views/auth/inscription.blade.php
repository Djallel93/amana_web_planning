{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>
    @vite(['resources/css/app.css'])
    {{--
    Styles de la section "Mes disponibilités par tâche" ci-dessous.
    Inlinés ici (plutôt que poussés via @push('scripts') comme dans
    resources/views/guide/index.blade.php) car cette vue est une page
    HTML autonome : elle n'étend pas layouts.app et n'a donc pas de
    @stack('scripts') pour les recevoir. Les variables CSS (--color-*)
    utilisées restent bien définies : elles viennent de la feuille
    partagée importée par resources/css/app.css (@vite ci-dessus),
    chargée sur cette page comme sur toutes les autres.

    Palette par tâche (--tache-accent / --tache-bg) : reprend les
    teintes déjà utilisées par .chip-* dans public/css/custom.css pour
    entree/mektaba/salle/amana_food/cours/absence (mêmes valeurs hex,
    pour rester visuellement cohérent avec le reste de l'app), et
    complète les codes qui n'ont pas de .chip-* défini côté planning
    (rappel_sandwich, assistance_amana_food, annonce_cours, message_bot,
    annulation_cours) avec des teintes assorties. Un code inconnu
    retombe sur la couleur accent fixe de l'app (#0369a1, voir
    tailwind-preset.js — "accent" n'est PAS une variable CSS comme
    surface/ink, c'est une couleur Tailwind figée) via .tache-card.
    --}}
    <style>
        /* ── Carte par tâche ── */
        .tache-card {
            --tache-accent: #0369a1;
            --tache-bg: rgb(var(--color-surface-2));
            border: 1px solid rgb(var(--color-surface-border));
            border-radius: 12px;
            background: rgb(var(--color-surface));
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(13, 17, 23, 0.04);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .tache-card:hover {
            box-shadow: 0 4px 14px rgba(13, 17, 23, 0.08);
            transform: translateY(-1px);
        }

        .tache-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px 14px;
            padding: 12px 16px;
            border-left: 4px solid var(--tache-accent);
            background: var(--tache-bg);
        }

        .tache-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: var(--tache-accent);
            background: rgb(var(--color-surface));
            border: 1px solid color-mix(in srgb, var(--tache-accent) 35%, transparent);
        }

        /* Couleurs par code — voir le commentaire Blade ci-dessus */
        .tache-entree {
            --tache-accent: #2563eb;
            --tache-bg: #eff6ff;
        }

        .tache-mektaba {
            --tache-accent: #059669;
            --tache-bg: #ecfdf5;
        }

        .tache-salle {
            --tache-accent: #d97706;
            --tache-bg: #fffbeb;
        }

        .tache-amana_food {
            --tache-accent: #e11d48;
            --tache-bg: #fff1f2;
        }

        .tache-cours {
            --tache-accent: #7c3aed;
            --tache-bg: #f5f3ff;
        }

        .tache-rappel_sandwich {
            --tache-accent: #ea580c;
            --tache-bg: #fff7ed;
        }

        .tache-assistance_amana_food {
            --tache-accent: #0891b2;
            --tache-bg: #ecfeff;
        }

        .tache-annonce_cours {
            --tache-accent: #4f46e5;
            --tache-bg: #eef2ff;
        }

        .tache-message_bot {
            --tache-accent: #0d9488;
            --tache-bg: #f0fdfa;
        }

        .tache-annulation_cours {
            --tache-accent: #dc2626;
            --tache-bg: #fef2f2;
        }

        .tache-absence {
            --tache-accent: #4b5563;
            --tache-bg: #f3f4f6;
        }

        /* ── Cases à cocher stylées en pastilles jour ── */
        .day-toggles {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .day-toggle {
            position: relative;
            display: inline-flex;
        }

        .day-toggle-input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .day-toggle-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 78px;
            min-height: 38px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            color: rgb(var(--color-ink-muted));
            background: rgb(var(--color-surface));
            border: 1.5px solid rgb(var(--color-surface-border));
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease,
                transform 0.1s ease;
        }

        .day-toggle-pill::before {
            content: "";
            width: 13px;
            height: 13px;
            border-radius: 4px;
            border: 1.5px solid rgb(var(--color-ink-faint));
            background: rgb(var(--color-surface));
            transition: background 0.15s ease, border-color 0.15s ease;
            flex-shrink: 0;
        }

        .day-toggle-input:hover+.day-toggle-pill {
            border-color: var(--tache-accent);
            color: var(--tache-accent);
        }

        .day-toggle-input:focus-visible+.day-toggle-pill {
            outline: 2px solid var(--tache-accent);
            outline-offset: 2px;
        }

        .day-toggle-input:checked+.day-toggle-pill {
            background: var(--tache-accent);
            border-color: var(--tache-accent);
            color: #fff;
            box-shadow: 0 2px 8px color-mix(in srgb, var(--tache-accent) 40%, transparent);
        }

        .day-toggle-input:checked+.day-toggle-pill::before {
            content: "✓";
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
            color: var(--tache-accent);
            background: #fff;
            border-color: #fff;
        }

        .day-toggle-input:active+.day-toggle-pill {
            transform: scale(0.96);
        }

        /* ── Description repliable ── */
        .tache-desc-toggle {
            border-top: 1px solid rgb(var(--color-surface-3));
        }

        .tache-desc-summary {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            color: rgb(var(--color-ink-muted));
            cursor: pointer;
            list-style: none;
            user-select: none;
            transition: color 0.15s ease;
        }

        .tache-desc-summary::-webkit-details-marker {
            display: none;
        }

        .tache-desc-toggle:hover .tache-desc-summary {
            color: var(--tache-accent);
        }

        .tache-desc-chevron {
            font-size: 11px;
            transition: transform 0.25s ease;
        }

        .tache-desc-toggle[open] .tache-desc-chevron {
            transform: rotate(180deg);
        }

        /* Rectangle de description — même esprit visuel que .guide-example
           dans resources/views/guide/index.blade.php (fond teinté, bordure
           gauche colorée), ici avec la couleur propre à la tâche plutôt
           qu'une couleur fixe. */
        .tache-desc-box {
            margin: 0 16px 14px 16px;
            padding: 11px 14px;
            border-radius: 8px;
            border-left: 3px solid var(--tache-accent);
            background: var(--tache-bg);
            font-size: 13px;
            line-height: 1.6;
            color: rgb(var(--color-ink-light));
            animation: tacheDescIn 0.2s ease-out both;
        }

        @keyframes tacheDescIn {
            0% {
                opacity: 0;
                transform: translateY(-4px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .tache-card,
            .tache-card:hover,
            .day-toggle-pill,
            .day-toggle-input:active+.day-toggle-pill,
            .tache-desc-chevron,
            .tache-desc-box {
                transition: none !important;
                animation: none !important;
                transform: none !important;
            }
        }
    </style>
</head>

<body class="bg-surface-2 font-body text-ink antialiased">

    {{-- Topbar --}}
    <header class="sticky top-0 z-50 bg-sidebar h-[54px] flex items-center justify-between px-4 sm:px-7">
        <a href="{{ route('login') }}" class="flex items-center gap-2.5 no-underline">
            <img src="{{ asset('favicon-96x96.png') }}" alt="AMANA" class="w-7 h-7 rounded-md object-cover">
            <span class="font-heading text-[15px] font-semibold text-white hidden sm:block">AMANA Planning</span>
        </a>
        <a href="{{ route('login') }}"
            class="text-[12.5px] text-white/55 border border-white/15 px-3.5 py-1.5 rounded-lg hover:text-white hover:border-white/40 transition-colors no-underline min-h-[44px] flex items-center">
            ← Connexion
        </a>
    </header>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-10 pb-16">
        <h1 class="font-heading text-2xl sm:text-[26px] font-semibold text-ink mb-1.5">Rejoindre AMANA Planning</h1>
        <p class="text-[13.5px] text-ink-muted mb-8 leading-relaxed">
            Remplissez ce formulaire pour soumettre votre candidature.<br>
            Un administrateur la validera et vous recevrez un email pour créer votre mot de passe.
        </p>

        @if($errors->any())
            <div
                class="flex items-start gap-2.5 px-4 py-3 rounded-lg mb-6 text-[13px] font-medium bg-rose-50 border border-rose-200 text-rose-800">
                ❌ Veuillez corriger les erreurs ci-dessous avant de soumettre.
            </div>
        @endif

        <form action="{{ route('inscription.submit') }}" method="POST">
            @csrf

            {{-- Informations personnelles --}}
            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-4">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div class="w-7 h-7 bg-violet-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">
                        👤</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Informations personnelles</span>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <div class="flex flex-col gap-1.5">
                            <label for="prenom" class="text-xs font-bold text-ink tracking-[0.2px]">
                                Prénom <span class="text-rose-500 ml-0.5">*</span>
                            </label>
                            <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" required
                                maxlength="100" placeholder="Votre prénom"
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                        focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                            @error('prenom')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="nom" class="text-xs font-bold text-ink tracking-[0.2px]">
                                Nom <span class="text-rose-500 ml-0.5">*</span>
                            </label>
                            <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="100"
                                placeholder="Votre nom de famille"
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                        focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                            @error('nom')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="email" class="text-xs font-bold text-ink tracking-[0.2px]">
                                Adresse email <span class="text-rose-500 ml-0.5">*</span>
                            </label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                maxlength="255" placeholder="votre@email.fr"
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                        focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                            <span class="text-xs text-ink-muted">Ce sera votre identifiant de connexion</span>
                            @error('email')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="telephone" class="text-xs font-bold text-ink tracking-[0.2px]">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}"
                                maxlength="20" placeholder="+33 6 00 00 00 00"
                                class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                                        focus:border-accent focus:bg-surface focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)] hover:border-ink-muted">
                            @error('telephone')<span class="text-xs text-rose-600 mt-0.5">{{ $message }}</span>@enderror
                        </div>

                    </div>
                </div>
            </div>

            {{-- Disponibilités --}}
            <div class="bg-surface rounded-xl border border-surface-border shadow-sm overflow-hidden mb-6">
                <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
                    <div
                        class="w-7 h-7 bg-emerald-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">
                        📋</div>
                    <span class="font-heading text-[14px] font-semibold text-ink">Mes disponibilités par tâche</span>
                </div>
                <div class="p-5">
                    <div
                        class="flex items-start gap-2.5 bg-surface-2 border border-surface-border rounded-lg px-4 py-3 mb-5 text-[13px] text-ink-muted leading-relaxed">
                        <span class="text-base flex-shrink-0 mt-0.5">ℹ️</span>
                        <span>Cochez les tâches que vous <strong class="text-ink-light">pouvez effectuer</strong> chaque
                            jour. Vous pourrez modifier ces disponibilités à tout moment depuis votre espace.</span>
                    </div>

                    {{-- Une carte colorée par tâche : nom + jours cochables sur la même
                    ligne dans l'en-tête, description repliée par défaut en dessous
                    (cliquer sur "ℹ️ Description de la tâche" pour la déplier). La
                    couleur (--tache-accent / --tache-bg, voir la feuille de styles
                    ci-dessus dans

                    <head>) reprend celle du chip de la tâche pour une identification rapide.
                        Remplace l'ancienne bulle d'info ⓘ au survol
                        (partials/tache-info-tooltip.blade.php, supprimé). --}}
                        <div class="flex flex-col gap-3">
                            @foreach($taches as $tache)
                                <div class="tache-card tache-{{ $tache->code }}">
                                    <div class="tache-card-header">
                                        <span class="tache-chip">{{ $tache->libelle }}</span>

                                        <div class="day-toggles">
                                            @foreach($jours as $jour)
                                                <label class="day-toggle">
                                                    <input type="checkbox" id="tache_{{ $tache->id }}_{{ $jour }}"
                                                        name="restrictions[{{ $tache->id }}][{{ $jour }}]" value="1" {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}
                                                        class="day-toggle-input">
                                                    <span class="day-toggle-pill">{{ $jour }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <details class="tache-desc-toggle">
                                        <summary class="tache-desc-summary">
                                            <span>ℹ️ Description de la tâche</span>
                                            <span class="tache-desc-chevron">▾</span>
                                        </summary>
                                        <div class="tache-desc-box">
                                            {{ $tache->description ?: 'Aucune description renseignée.' }}
                                        </div>
                                    </details>
                                </div>
                            @endforeach
                        </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                <button type="submit" class="min-h-[48px] px-7 py-3 bg-accent hover:bg-accent-dark text-white font-bold text-sm rounded-lg
                    shadow-[0_3px_14px_rgba(3,105,161,0.35)] hover:shadow-[0_6px_20px_rgba(3,105,161,0.45)]
                    hover:-translate-y-px active:translate-y-0 transition-all cursor-pointer text-center">
                    ✉️ Soumettre ma candidature
                </button>
                <p class="text-xs text-ink-muted leading-relaxed">
                    En soumettant ce formulaire, vous acceptez que vos informations<br>
                    soient utilisées dans le cadre du bénévolat AMANA.
                </p>
            </div>

        </form>
    </div>

</body>

</html>