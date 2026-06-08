{{-- resources/views/layouts/partials/flash.blade.php --}}
{{--
Affiche les messages flash de session.
Inclus automatiquement dans layouts/app.blade.php.
Peut aussi être utilisé individuellement dans d'autres layouts.
--}}

@if(session('success'))
    <div class="flash flash-success" role="alert">
        <span>✅</span>
        <span>{{ session('success') }}</span>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
    </div>
@endif

@if(session('error'))
    <div class="flash flash-error" role="alert">
        <span>❌</span>
        <span>{{ session('error') }}</span>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
    </div>
@endif

@if(session('warning'))
    <div class="flash flash-warning" role="alert">
        <span>⚠️</span>
        <span>{{ session('warning') }}</span>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
    </div>
@endif

@if(session('info'))
    <div class="flash flash-info" role="alert">
        <span>ℹ️</span>
        <span>{{ session('info') }}</span>
        <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
    </div>
@endif