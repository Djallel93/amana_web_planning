{{-- resources/views/layouts/partials/sidebar.blade.php --}}

{{-- ── Mobile topbar ── --}}
<div class="mobile-topbar" id="mobileTopbar">
    <a href="{{ route('planning.index') }}" class="mobile-topbar-logo">
        <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="mobile-topbar-logo-img">
        <span class="mobile-topbar-name">AMANA</span>
    </a>
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</div>

{{-- ── Overlay mobile ── --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

{{-- ── Sidebar ── --}}
<aside class="sidebar" id="mainSidebar" aria-label="Navigation principale">

    <div class="sidebar-brand">
        <a href="{{ route('planning.index') }}" class="sidebar-logo">
            <img src="{{ asset('images/amana-logo.png') }}" alt="AMANA" class="sidebar-logo-img">
            <div class="sidebar-logo-text">
                <span class="sidebar-logo-name">AMANA</span>
                <span class="sidebar-logo-sub">Planning</span>
            </div>
        </a>
    </div>

    <div class="sidebar-section">

        {{-- Badge rôle --}}
        @auth
            @if(auth()->user()->isAdmin())
                <div class="role-badge admin">🛡️ Administrateur</div>
            @elseif(auth()->user()->isGestionnaire())
                <div class="role-badge gestionnaire">⚙️ Gestionnaire</div>
            @else
                <div class="role-badge membre">👤 Membre</div>
            @endif
        @endauth

        {{-- Planning --}}
        <div class="sidebar-label">Planning</div>

        <a href="{{ route('planning.index') }}"
            class="nav-item {{ request()->routeIs('planning.index') ? 'active' : '' }}" onclick="closeSidebar()">
            <span class="nav-icon">📅</span>
            <span class="nav-text">Planning</span>
        </a>

        <a href="{{ route('planning.statistics') }}"
            class="nav-item {{ request()->routeIs('planning.statistics') ? 'active' : '' }}" onclick="closeSidebar()">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Statistiques</span>
        </a>

        <a href="{{ route('planning.export.form') }}"
            class="nav-item {{ request()->routeIs('planning.export*') ? 'active' : '' }}" onclick="closeSidebar()">
            <span class="nav-icon">📄</span>
            <span class="nav-text">Export PDF</span>
        </a>

        {{-- Mes données --}}
        <div class="sidebar-label">Mes données</div>

        <a href="{{ route('absences.index') }}" class="nav-item {{ request()->routeIs('absences.*') ? 'active' : '' }}"
            onclick="closeSidebar()">
            <span class="nav-icon">🏖️</span>
            <span class="nav-text">Absences</span>
        </a>

        <a href="{{ route('restrictions.index') }}"
            class="nav-item {{ request()->routeIs('restrictions.*') ? 'active' : '' }}" onclick="closeSidebar()">
            <span class="nav-icon">🔒</span>
            <span class="nav-text">Disponibilités</span>
        </a>

        {{-- Gestion : gestionnaire + admin --}}
        @auth
            @if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
                <div class="sidebar-label">Gestion</div>

                <a href="{{ route('planning.generate.form') }}"
                    class="nav-item {{ request()->routeIs('planning.generate*') ? 'active' : '' }}" onclick="closeSidebar()">
                    <span class="nav-icon">✨</span>
                    <span class="nav-text">Générer</span>
                </a>

                <a href="{{ route('evenements.index') }}"
                    class="nav-item {{ request()->routeIs('evenements.*') ? 'active' : '' }}" onclick="closeSidebar()">
                    <span class="nav-icon">🎉</span>
                    <span class="nav-text">Événements</span>
                </a>

                {{-- Guard Route::has pour éviter l'erreur si web.php n'est pas encore mis à jour --}}
                @if(Route::has('settings.index'))
                    <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}"
                        onclick="closeSidebar()">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-text">Paramètres</span>
                    </a>
                @endif
            @endif
        @endauth

        {{-- Administration : admin uniquement --}}
        @auth
            @if(auth()->user()->isAdmin())
                <div class="sidebar-label">Administration</div>

                <a href="{{ route('personnes.index') }}"
                    class="nav-item {{ request()->routeIs('personnes.*') ? 'active' : '' }}" onclick="closeSidebar()">
                    <span class="nav-icon">👥</span>
                    <span class="nav-text">Personnes</span>
                </a>

                @php $nbCandidatures = \App\Models\Personne::enAttente()->count(); @endphp
                <a href="{{ route('admin.candidatures.index') }}"
                    class="nav-item {{ request()->routeIs('admin.candidatures*') ? 'active' : '' }}" onclick="closeSidebar()">
                    <span class="nav-icon">📥</span>
                    <span class="nav-text">Candidatures</span>
                    @if($nbCandidatures > 0)
                        <span class="nav-badge">{{ $nbCandidatures }}</span>
                    @endif
                </a>
            @endif
        @endauth

    </div>{{-- /.sidebar-section --}}

    {{-- Pied de sidebar : utilisateur connecté + déconnexion --}}
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                {{ strtoupper(substr(auth()->user()->prenom ?? 'A', 0, 1)) }}
            </div>
            <div class="user-info">
                <div class="user-name">
                    {{ auth()->user()->prenom ?? '' }} {{ auth()->user()->nom ?? '' }}
                </div>
                <div class="user-role">
                    @if(auth()->user()->isAdmin()) Administrateur
                    @elseif(auth()->user()->isGestionnaire()) Gestionnaire
                    @else Membre
                    @endif
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn-logout-sidebar" title="Déconnexion">↪</button>
            </form>
        </div>
    </div>

</aside>