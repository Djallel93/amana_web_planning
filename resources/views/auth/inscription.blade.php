{{-- resources/views/auth/inscription.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — AMANA Planning</title>
    @vite(['resources/css/app.css'])
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

                    {{-- Table desktop (≥ sm) --}}
                    <div class="hidden sm:block overflow-x-auto -webkit-overflow-scrolling-touch">
                        <table class="w-full border-collapse text-[13px] min-w-[380px]">
                            <thead>
                                <tr>
                                    <th
                                        class="text-left pl-4 py-2.5 text-[10.5px] font-bold text-ink-muted uppercase tracking-[0.7px] bg-surface-2 border-b border-surface-3 font-body">
                                        Tâche</th>
                                    @foreach($jours as $jour)
                                        <th
                                            class="text-center py-2.5 px-3 bg-accent text-white text-xs font-semibold border-b border-surface-3">
                                            {{ $jour }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($taches as $tache)
                                    <tr
                                        class="border-b border-surface-3 last:border-0 hover:bg-surface-2 transition-colors">
                                        <td class="pl-4 py-2.5 font-semibold text-ink">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold chip-{{ $tache->code }}">
                                                {{ $tache->libelle }}
                                            </span>
                                        </td>
                                        @foreach($jours as $jour)
                                            <td class="text-center py-2.5 px-3">
                                                <input type="checkbox" name="restrictions[{{ $tache->id }}][{{ $jour }}]"
                                                    value="1" title="{{ $tache->libelle }} — {{ $jour }}" {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}
                                                    class="w-4 h-4 accent-accent cursor-pointer">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Cartes mobile (< sm) --}} <div class="sm:hidden flex flex-col gap-3">
                        @foreach($taches as $tache)
                            <div class="border border-surface-border rounded-lg overflow-hidden">
                                <div class="px-4 py-2.5 bg-surface-2 font-semibold text-[13px] text-ink flex items-center">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold chip-{{ $tache->code }}">
                                        {{ $tache->libelle }}
                                    </span>
                                </div>
                                <div class="px-4 py-3 flex flex-col gap-2.5">
                                    @foreach($jours as $jour)
                                        <label for="mob_{{ $tache->id }}_{{ $jour }}"
                                            class="flex items-center gap-2.5 text-[13px] text-ink-light cursor-pointer min-h-[44px]">
                                            <input type="checkbox" id="mob_{{ $tache->id }}_{{ $jour }}"
                                                name="restrictions[{{ $tache->id }}][{{ $jour }}]" value="1" {{ old('restrictions.' . $tache->id . '.' . $jour) ? 'checked' : '' }}
                                                class="w-4 h-4 accent-accent cursor-pointer flex-shrink-0">
                                            {{ $jour }}
                                        </label>
                                    @endforeach
                                </div>
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