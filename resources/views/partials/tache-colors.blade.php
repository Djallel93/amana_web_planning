{{--
resources/views/partials/tache-colors.blade.php

Définit la variable $tacheColors utilisée dans les vues qui affichent
des badges de tâches colorés (evenements/form, evenements/index, etc.)

Usage :
@include('partials.tache-colors')
{{-- $tacheColors est maintenant disponible --}}
--}}
@php
    $tacheColors = [
        'entree' => ['bg' => '#eff6ff', 'color' => '#2563eb', 'icon' => '🚪'],
        'mektaba' => ['bg' => '#ecfdf5', 'color' => '#059669', 'icon' => '📚'],
        'salle' => ['bg' => '#fffbeb', 'color' => '#d97706', 'icon' => '🏛️'],
        'amana_food' => ['bg' => '#fff1f2', 'color' => '#e11d48', 'icon' => '🥪'],
        'cours' => ['bg' => '#f5f3ff', 'color' => '#7c3aed', 'icon' => '🎓'],
    ];
@endphp