{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

@include('layouts.partials.head')

<body class="bg-surface-2 font-body text-ink antialiased flex min-h-screen">

    @include('layouts.partials.sidebar')

    {{-- ── Contenu principal ── --}}
    <div id="mainWrapper"
        class="flex-1 flex flex-col min-w-0 ml-sidebar transition-all duration-300 max-sm:ml-0 max-sm:pt-topbar">
        <main class="flex-1 p-8 max-w-screen-xl w-full mx-auto max-lg:p-7 max-sm:px-4 max-sm:py-5">

            @include('layouts.partials.flash')

            @yield('content')

        </main>
    </div>

    {{--
        Point de montage MobileSidebar.vue — gère l'ouverture/fermeture mobile
        et le collapse desktop de #mainSidebar. Le markup de la sidebar reste
        dans layouts/partials/sidebar.blade.php ; ce composant pilote uniquement
        ses classes CSS et rend le bouton collapse flottant.
    --}}
    <div id="vue-mobile-sidebar"></div>

    @stack('scripts')

    {{--
        Point de montage du composant Toast.vue (Vue 3).
        Ce div vide est remplacé par le composant au chargement — il n'a
        pas besoin de contenu HTML. Il doit être présent sur TOUTES les pages
        car le layout principal inclut cette vue.
        Les <div id="toastContainer"> dans les Blade individuelles seront
        supprimés au fur et à mesure que leurs pages sont converties en Vue.
    --}}
    <div id="vue-toast"></div>

    {{--
        Point de montage ConfirmDialog.vue — boîte de confirmation stylée
        partagée par toute l'app (voir composable useConfirm), en
        remplacement de confirm() natif du navigateur. Une seule instance
        pour toute la page, comme Toast.vue.
    --}}
    <div id="vue-confirm-dialog"></div>

    {{--
        Point de montage OfflineBanner.vue — bannière affichée en haut de
        la page quand le navigateur détecte une perte de connexion.
    --}}
    <div id="vue-offline-banner"></div>

</body>

</html>