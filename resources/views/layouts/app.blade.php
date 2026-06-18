{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

@include('partials.head')

<body>

    @include('layouts.partials.sidebar')

    {{-- ── Contenu principal ── --}}
    <div class="main-wrapper">
        <main class="main-content">

            @include('layouts.partials.flash')

            @yield('content')

        </main>
    </div>

    {{-- ── Script sidebar mobile ── --}}
    <script>
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburgerBtn');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('visible');
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        }

        hamburger.addEventListener('click', toggleSidebar);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
        window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });
    </script>

    @stack('scripts')

</body>

</html>