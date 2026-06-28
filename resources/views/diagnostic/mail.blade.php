{{-- resources/views/diagnostic/mail.blade.php --}}
@extends('layouts.app')

@section('title', 'Diagnostic SMTP — AMANA')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-ink tracking-tight">🔧 Diagnostic SMTP</h1>
        <p class="text-[13px] text-ink-muted mt-1">Test de la configuration email en production</p>
    </div>
</div>

{{-- Configuration active --}}
<div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
        <div class="w-7 h-7 bg-sky-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">📋</div>
        <span class="font-heading text-[14px] font-semibold text-ink">Configuration SMTP active</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[13.5px]">
            <tbody>
                @foreach([
                    'Mailer'           => $config['mailer'],
                    'Host'             => $config['host'],
                    'Port'             => $config['port'],
                    'Scheme'           => $config['scheme'],
                    'Username'         => $config['username'],
                    'Password'         => $config['password'],
                    'From address'     => $config['from_address'],
                    'From name'        => $config['from_name'],
                    'Queue connection' => $config['queue'],
                    'Log channel'      => $config['log_channel'],
                ] as $label => $val)
                    <tr class="border-b border-surface-3 last:border-0">
                        <td class="px-5 py-3 font-semibold text-ink-muted whitespace-nowrap w-44">{{ $label }}</td>
                        <td class="px-5 py-3 font-mono text-ink">
                            @if($label === 'Scheme' && $val === 'null')
                                <span class="text-rose-600 font-bold">{{ $val }}</span>
                                <span class="text-[11.5px] text-rose-500 ml-2">
                                    ⚠️ La valeur littérale "null" peut bloquer STARTTLS
                                </span>
                            @elseif(in_array($label, ['Scheme', 'Password']) && empty($val))
                                <span class="text-ink-muted">(vide)</span>
                            @else
                                {{ $val }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Avertissement MAIL_SCHEME --}}
@if(($config['scheme'] ?? '') === 'null')
    <div class="flex items-start gap-3 px-5 py-4 bg-rose-50 border border-rose-200 rounded-xl mb-5 text-[13px] text-rose-800 leading-relaxed">
        <span class="text-base flex-shrink-0 mt-0.5">⚠️</span>
        <div>
            <strong>Problème détecté : MAIL_SCHEME=null</strong><br>
            La valeur <code class="bg-rose-100 px-1 rounded text-[12px]">null</code> dans le <code class="bg-rose-100 px-1 rounded text-[12px]">.env</code>
            est lue comme la chaîne <em>"null"</em> par Laravel, pas comme une valeur PHP null.
            Symfony Mailer peut l'interpréter comme un schéma invalide et bloquer la connexion STARTTLS.<br>
            <strong>Correction :</strong> dans votre <code class="bg-rose-100 px-1 rounded text-[12px]">.env</code>,
            remplacez <code class="bg-rose-100 px-1 rounded text-[12px]">MAIL_SCHEME=null</code>
            par <code class="bg-rose-100 px-1 rounded text-[12px]">MAIL_SCHEME=</code> (vide)
            ou <code class="bg-rose-100 px-1 rounded text-[12px]">MAIL_SCHEME=tls</code>,
            puis relancez <code class="bg-rose-100 px-1 rounded text-[12px]">php artisan config:cache</code>.
        </div>
    </div>
@endif

{{-- Résultat du test --}}
@if($resultat !== null)
    <div class="bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden mb-5">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
            <div class="w-7 h-7 rounded-md flex items-center justify-center text-sm flex-shrink-0
                        {{ $resultat['succes'] ? 'bg-emerald-50' : 'bg-rose-50' }}">
                {{ $resultat['succes'] ? '✅' : '❌' }}
            </div>
            <span class="font-heading text-[14px] font-semibold text-ink">
                Résultat du test — {{ $testEmail }}
            </span>
        </div>
        <div class="p-5">
            @if($resultat['succes'])
                <div class="flex items-start gap-2.5 px-4 py-3.5 bg-emerald-50 border border-emerald-200 rounded-lg text-[13.5px] text-emerald-800 mb-4">
                    <span class="flex-shrink-0">✅</span>
                    <div>
                        <strong>Email envoyé avec succès</strong> en {{ $resultat['duree_ms'] }}ms.<br>
                        Vérifiez la boîte de réception de <strong>{{ $testEmail }}</strong> (et le dossier spam).
                    </div>
                </div>
            @else
                <div class="flex items-start gap-2.5 px-4 py-3.5 bg-rose-50 border border-rose-200 rounded-lg text-[13.5px] text-rose-800 mb-4">
                    <span class="flex-shrink-0">❌</span>
                    <div>
                        <strong>Échec après {{ $resultat['duree_ms'] }}ms.</strong><br>
                        <code class="text-[12px] break-all">{{ $resultat['erreur'] }}</code>
                    </div>
                </div>
                @if($resultat['trace'])
                    <details class="mt-2">
                        <summary class="cursor-pointer text-[12.5px] text-ink-muted font-semibold hover:text-ink">
                            Trace complète (développeurs)
                        </summary>
                        <pre class="mt-3 bg-surface-2 border border-surface-border rounded-lg p-4 text-[11px] overflow-x-auto text-ink-light leading-relaxed">{{ $resultat['trace'] }}</pre>
                    </details>
                @endif
            @endif
            <p class="text-[12px] text-ink-muted mt-3">
                💾 Le résultat complet a été enregistré dans <code class="bg-surface-3 px-1 rounded text-[11px]">storage/logs/laravel.log</code>.
            </p>
        </div>
    </div>
@endif

{{-- Formulaire de test --}}
<div class="max-w-[480px] bg-white rounded-xl border border-surface-border shadow-sm overflow-hidden">
    <div class="flex items-center gap-2.5 px-5 py-4 border-b border-surface-3">
        <div class="w-7 h-7 bg-amber-50 rounded-md flex items-center justify-center text-sm flex-shrink-0">✉️</div>
        <span class="font-heading text-[14px] font-semibold text-ink">Envoyer un email de test</span>
    </div>
    <div class="p-5">
        <p class="text-[13px] text-ink-muted mb-5 leading-relaxed">
            Saisissez une adresse email accessible pour vérifier que le SMTP fonctionne.
            Le résultat sera aussi tracé dans <code class="bg-surface-3 px-1 rounded text-[11.5px]">laravel.log</code>.
        </p>
        <form action="{{ route('diagnostic.mail.tester') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label for="email" class="text-xs font-bold text-ink tracking-[0.2px]">Adresse email de test</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email', $testEmail) }}" placeholder="votre@email.com" required
                       class="w-full px-3.5 py-2.5 border-[1.5px] border-ink-faint rounded-lg text-base font-body text-ink bg-surface-2 outline-none transition
                              focus:border-accent focus:bg-white focus:shadow-[0_0_0_3px_rgba(3,105,161,0.2)]">
                @error('email')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-accent hover:bg-accent-dark text-white font-bold text-[13.5px] rounded-lg
                           shadow-[0_3px_12px_rgba(3,105,161,0.3)] hover:-translate-y-px transition-all cursor-pointer min-h-[48px]">
                🚀 Tester l'envoi
            </button>
        </form>
    </div>
</div>

@endsection
