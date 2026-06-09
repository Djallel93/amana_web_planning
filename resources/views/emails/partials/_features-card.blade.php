{{-- resources/views/emails/partials/_features-card.blade.php --}}
{{--
Variable:
$featuresLabel — e.g. 'Une fois connecté, vous pourrez'
or 'Depuis AMANA Planning, vous pouvez'
--}}
<div class="features-card">
    <div class="features-label">&#10022; &nbsp; {{ $featuresLabel }}</div>
    <div class="feature-row">
        <div class="feature-icon">📅</div>
        <div class="feature-text"><strong>Consulter le planning</strong> des permanences vendredis &amp; samedis</div>
    </div>
    <div class="feature-row">
        <div class="feature-icon">📄</div>
        <div class="feature-text"><strong>Télécharger le planning</strong> au format PDF</div>
    </div>
    <div class="feature-row">
        <div class="feature-icon">🏖️</div>
        <div class="feature-text"><strong>Déclarer vos absences</strong> pour que le planning soit ajusté</div>
    </div>
    <div class="feature-row">
        <div class="feature-icon">🔒</div>
        <div class="feature-text"><strong>Gérer vos disponibilités</strong> par tâche et par jour</div>
    </div>
</div>