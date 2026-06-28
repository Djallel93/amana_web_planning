{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

@include('layouts.partials.head')

<body class="bg-surface-2 font-body text-ink antialiased flex min-h-screen">

    @include('layouts.partials.sidebar')

    {{-- ── Contenu principal ── --}}
    <div id="mainWrapper" class="flex-1 flex flex-col min-w-0 ml-sidebar transition-all duration-300 max-sm:ml-0 max-sm:pt-topbar">
        <main class="flex-1 p-8 max-w-screen-xl w-full max-lg:p-7 max-sm:px-4 max-sm:py-5">

            @include('layouts.partials.flash')

            @yield('content')

        </main>
    </div>

    {{-- ── Script sidebar mobile ── --}}
    <script>
        const sidebar  = document.getElementById('mainSidebar');
        const overlay  = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburgerBtn');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('shadow-lg');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-100');
            hamburger.classList.add('hamburger-open');
            hamburger.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('shadow-lg');
            overlay.classList.add('opacity-0', 'pointer-events-none');
            overlay.classList.remove('opacity-100');
            hamburger.classList.remove('hamburger-open');
            hamburger.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        function toggleSidebar() {
            sidebar.classList.contains('-translate-x-full') ? openSidebar() : closeSidebar();
        }

        hamburger.addEventListener('click', toggleSidebar);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
        window.addEventListener('resize', () => { if (window.innerWidth > 640) closeSidebar(); });
    </script>

    @stack('scripts')

</body>
</html>
