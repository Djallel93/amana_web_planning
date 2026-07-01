{{-- resources/views/layouts/partials/sidebar.blade.php --}}

{{-- ── Mobile topbar ── --}}
<div
    class="sm:hidden fixed top-0 left-0 right-0 h-topbar bg-sidebar z-[300] flex items-center justify-between px-4 border-b border-white/[0.06]">
    <a href="{{ route('planning.index') }}" class="flex items-center gap-2.5 no-underline">
        <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA"
            class="w-8 h-8 rounded-md object-cover flex-shrink-0">
        <span class="font-heading text-[15px] font-semibold text-white">AMANA</span>
    </a>
    <button id="hamburgerBtn"
        class="flex flex-col gap-[5px] items-center justify-center w-10 h-10 rounded-md text-white/70 hover:bg-white/10 hover:text-white/75 transition-colors"
        aria-label="Menu" aria-expanded="false">
        <span class="hamburger-bar"></span>
        <span class="hamburger-bar"></span>
        <span class="hamburger-bar"></span>
    </button>
</div>

{{-- ── Overlay mobile ── --}}
<div id="sidebarOverlay" onclick="closeSidebar()"
    class="sm:hidden fixed inset-0 bg-black/50 z-[198] opacity-0 pointer-events-none">
</div>

{{-- ── Sidebar ── --}}
<aside id="mainSidebar"
    class="w-sidebar min-h-screen bg-sidebar flex flex-col fixed top-0 left-0 bottom-0 z-[200] overflow-hidden sidebar-hidden"
    aria-label="Navigation principale">

    {{-- Brand --}}
    <div class="px-5 py-[22px] pb-[18px] border-b border-white/[0.06] relative z-10">
        <a href="{{ route('planning.index') }}" class="flex items-center gap-[11px] no-underline">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA"
                class="w-[38px] h-[38px] rounded-[9px] object-cover flex-shrink-0 shadow-[0_4px_12px_rgba(0,0,0,0.3)]">
            <div class="flex flex-col">
                <span
                    class="font-heading text-[16px] font-semibold text-white leading-none tracking-[0.2px]">AMANA</span>
                <span class="text-[10px] text-white/35 tracking-widest uppercase font-medium mt-0.5">Planning</span>
            </div>
        </a>
    </div>

    {{-- Nav section --}}
    <div class="px-3.5 py-4 flex-1 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden relative z-10">

        {{-- Badge rôle --}}
        @auth
            @if(auth()->user()->isAdmin())
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                                        bg-rose-500/[0.14] text-rose-300 border border-rose-500/[0.22]">
                    🛡️ Administrateur
                </div>
            @elseif(auth()->user()->isGestionnaire())
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                                        bg-amber-500/[0.14] text-amber-300 border border-amber-500/[0.22]">
                    ⚙️ Gestionnaire
                </div>
            @else
                <div
                    class="mx-1 mb-2.5 px-[11px] py-[7px] rounded-sm text-[11px] font-semibold flex items-center gap-1.5
                                                                                        bg-sky-500/[0.14] text-sky-300 border border-sky-500/[0.22]">
                    👤 Membre
                </div>
            @endif
        @endauth

        {{-- Section : Planning --}}
        <p class="px-2.5 mb-1 mt-3 first:mt-0 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">Planning
        </p>

        <a href="{{ route('planning.index') }}"
            class="nav-item-active relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('planning.index') ? 'bg-sky-500/15 text-white font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">📅</span>
            <span class="flex-1">Planning</span>
        </a>

        <a href="{{ route('mon-planning') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('mon-planning') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">🙋</span>
            <span class="flex-1">Mon planning</span>
        </a>

        <a href="{{ route('echanges.index') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('echanges.index') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">🔄</span>
            <span class="flex-1">Mes échanges</span>
            @php
                $nbEchangesMembre = \App\Models\Echange::enAttente()
                    ->impliquant(auth()->id())
                    ->count();
            @endphp
            @if($nbEchangesMembre > 0)
                <span class="nav-badge bg-rose-500 text-white text-[10px] font-bold px-[7px] py-px rounded-full">
                    {{ $nbEchangesMembre }}
                </span>
            @endif
        </a>

        <a href="{{ route('planning.statistics') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('planning.statistics') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">📊</span>
            <span class="flex-1">Statistiques</span>
        </a>

        <a href="{{ route('planning.export.form') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('planning.export*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">📄</span>
            <span class="flex-1">Export PDF</span>
        </a>

        {{-- Section : Mes données --}}
        <p class="px-2.5 mb-1 mt-3 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">Mes données</p>

        <a href="{{ route('absences.index') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('absences.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">🏖️</span>
            <span class="flex-1">Absences</span>
        </a>

        <a href="{{ route('restrictions.index') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('restrictions.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">🔒</span>
            <span class="flex-1">Disponibilités</span>
        </a>

        {{-- Section : Bilan --}}
        <p class="px-2.5 mb-1 mt-3 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">Bilan</p>

        <a href="{{ route('bilan.index') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('bilan.index') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">🧾</span>
            <span class="flex-1">Saisie</span>
        </a>

        <a href="{{ route('bilan.statistiques') }}"
            class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                {{ request()->routeIs('bilan.statistiques') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
            onclick="closeSidebar()">
            <span class="text-sm w-[18px] text-center flex-shrink-0">📊</span>
            <span class="flex-1">Statistiques</span>
        </a>

        {{-- Section : Gestion (gestionnaire + admin) --}}
        @auth
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <p class="px-2.5 mb-1 mt-3 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">Gestion</p>

                <a href="{{ route('planning.generate.form') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                        {{ request()->routeIs('planning.generate*') || request()->routeIs('planning.preview') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">✨</span>
                    <span class="flex-1">Générer</span>
                </a>

                <a href="{{ route('evenements.index') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                        {{ request()->routeIs('evenements.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">🎉</span>
                    <span class="flex-1">Événements</span>
                </a>

                @php $nbEchangesAdmin = \App\Models\Echange::enAttente()->count(); @endphp
                <a href="{{ route('admin.echanges.index') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                        {{ request()->routeIs('admin.echanges.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">🔄</span>
                    <span class="flex-1">Échanges</span>
                    @if($nbEchangesAdmin > 0)
                        <span class="nav-badge bg-rose-500 text-white text-[10px] font-bold px-[7px] py-px rounded-full">
                            {{ $nbEchangesAdmin }}
                        </span>
                    @endif
                </a>

                @if(Route::has('settings.index'))
                    <a href="{{ route('settings.index') }}"
                        class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                                                            {{ request()->routeIs('settings.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                        onclick="closeSidebar()">
                        <span class="text-sm w-[18px] text-center flex-shrink-0">⚙️</span>
                        <span class="flex-1">Paramètres</span>
                    </a>
                @endif
            @endif
        @endauth

        {{-- Section : Administration (admin uniquement) --}}
        @auth
            @if(auth()->user()->isAdmin())
                <p class="px-2.5 mb-1 mt-3 text-[9.5px] font-bold tracking-[1.4px] uppercase text-white/20">Administration</p>

                <a href="{{ route('personnes.index') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                        {{ request()->routeIs('personnes.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">👥</span>
                    <span class="flex-1">Personnes</span>
                </a>

                @php $nbCandidatures = \App\Models\Personne::enAttente()->count(); @endphp
                <a href="{{ route('admin.candidatures.index') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                        {{ request()->routeIs('admin.candidatures*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">📥</span>
                    <span class="flex-1">Candidatures</span>
                    @if($nbCandidatures > 0)
                        <span class="nav-badge bg-rose-500 text-white text-[10px] font-bold px-[7px] py-px rounded-full">
                            {{ $nbCandidatures }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('diagnostic.mail.index') }}"
                    class="relative flex items-center gap-2.5 px-3 py-2 rounded-sm text-[13px] font-medium transition-colors mb-px no-underline
                                                                                    {{ request()->routeIs('diagnostic.mail.*') ? 'nav-item-active bg-sky-500/15 text-amber-300 font-semibold' : 'text-white hover:bg-white/[0.06] hover:text-white/75' }}"
                    onclick="closeSidebar()">
                    <span class="text-sm w-[18px] text-center flex-shrink-0">🔧</span>
                    <span class="flex-1">Diagnostic SMTP</span>
                </a>
            @endif
        @endauth

    </div>

    {{-- Footer utilisateur --}}
    <div class="px-3.5 py-3.5 border-t border-white/[0.06]">
        <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-sm bg-white/[0.05]">
            <div
                class="w-8 h-8 bg-accent rounded-full flex items-center justify-center text-xs text-white font-bold flex-shrink-0">
                {{ strtoupper(substr(auth()->user()->prenom ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0 overflow-hidden">
                <div class="text-[12.5px] text-white/80 font-semibold truncate">
                    {{ auth()->user()->prenom ?? '' }} {{ auth()->user()->nom ?? '' }}
                </div>
                <div class="text-[11px] text-white/32 mt-px">
                    @if(auth()->user()->isAdmin()) Administrateur
                    @elseif(auth()->user()->isGestionnaire()) Gestionnaire
                    @else Membre
                    @endif
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                    class="text-white/30 hover:text-rose-400 text-base p-1 rounded transition-colors bg-transparent border-0 cursor-pointer leading-none flex-shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    title="Déconnexion">↪</button>
            </form>
        </div>
    </div>

</aside>