{{-- resources/views/diagnostic/mail.blade.php --}}
@extends('layouts.app')

@section('title', 'Diagnostic SMTP — AMANA')

@section('content')
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-title">🔧 Diagnostic SMTP</div>
            <div class="page-subtitle">Test de la configuration email en production</div>
        </div>
    </div>

    {{-- ── Configuration active ──────────────────────────────────────────── --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--sky-bg);">📋</div>
                Configuration SMTP active
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
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
                        <tr style="border-bottom:1px solid var(--surface-3);">
                            <td style="padding:11px 20px;font-weight:600;color:var(--ink-muted);width:180px;white-space:nowrap;">
                                {{ $label }}
                            </td>
                            <td style="padding:11px 20px;font-family:monospace;color:var(--ink);">
                                @if($label === 'Scheme' && $val === 'null')
                                    <span style="color:var(--rose);font-weight:600;">{{ $val }}</span>
                                    <span style="font-size:11.5px;color:var(--rose);margin-left:8px;">
                                        ⚠️ La valeur littérale "null" peut bloquer STARTTLS — utilisez une valeur vide ou "tls"
                                    </span>
                                @elseif(in_array($label, ['Scheme', 'Password']) && empty($val))
                                    <span style="color:var(--ink-muted);">(vide)</span>
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

    {{-- ── Avertissement MAIL_SCHEME ──────────────────────────────────────── --}}
    @if(($config['scheme'] ?? '') === 'null')
        <div style="background:var(--rose-bg);border:1px solid var(--rose-border);border-radius:var(--radius);padding:14px 18px;margin-bottom:20px;font-size:13px;color:#9f1239;line-height:1.7;">
            <strong>⚠️ Problème détecté : MAIL_SCHEME=null</strong><br>
            La valeur <code>null</code> dans le <code>.env</code> est lue comme la chaîne <em>"null"</em> par Laravel,
            pas comme une valeur PHP null. Symfony Mailer peut l'interpréter comme un schéma invalide et bloquer la connexion STARTTLS.<br>
            <strong>Correction :</strong> dans votre <code>.env</code>, remplacez <code>MAIL_SCHEME=null</code>
            par <code>MAIL_SCHEME=</code> (valeur vide) ou <code>MAIL_SCHEME=tls</code>, puis relancez
            <code>php artisan config:cache</code>.
        </div>
    @endif

    {{-- ── Résultat du test ──────────────────────────────────────────────── --}}
    @if($resultat !== null)
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header">
                <div class="card-title">
                    <div class="card-title-icon"
                        style="background:{{ $resultat['succes'] ? 'var(--emerald-bg)' : 'var(--rose-bg)' }};">
                        {{ $resultat['succes'] ? '✅' : '❌' }}
                    </div>
                    Résultat du test — {{ $testEmail }}
                </div>
            </div>
            <div class="card-body">
                @if($resultat['succes'])
                    <div style="background:var(--emerald-bg);border:1px solid var(--emerald-border);border-radius:var(--radius);padding:14px 18px;color:#065f46;font-size:13.5px;margin-bottom:16px;">
                        ✅ <strong>Email envoyé avec succès</strong> en {{ $resultat['duree_ms'] }}ms.<br>
                        Vérifiez la boîte de réception de <strong>{{ $testEmail }}</strong>
                        (et le dossier spam).
                    </div>
                @else
                    <div style="background:var(--rose-bg);border:1px solid var(--rose-border);border-radius:var(--radius);padding:14px 18px;color:#9f1239;font-size:13.5px;margin-bottom:16px;">
                        ❌ <strong>Échec après {{ $resultat['duree_ms'] }}ms.</strong><br>
                        <code style="font-size:12px;word-break:break-all;">{{ $resultat['erreur'] }}</code>
                    </div>

                    @if($resultat['trace'])
                        <details style="margin-top:12px;">
                            <summary style="cursor:pointer;font-size:12.5px;color:var(--ink-muted);font-weight:600;">
                                Trace complète (développeurs)
                            </summary>
                            <pre style="margin-top:10px;background:var(--surface-2);padding:14px;border-radius:var(--radius);font-size:11px;overflow-x:auto;color:var(--ink-light);line-height:1.5;">{{ $resultat['trace'] }}</pre>
                        </details>
                    @endif
                @endif

                <div style="font-size:12px;color:var(--ink-muted);margin-top:8px;">
                    💾 Le résultat complet a été enregistré dans <code>storage/logs/laravel.log</code>.
                </div>
            </div>
        </div>
    @endif

    {{-- ── Formulaire de test ────────────────────────────────────────────── --}}
    <div class="card" style="max-width:480px;">
        <div class="card-header">
            <div class="card-title">
                <div class="card-title-icon" style="background:var(--amber-bg);">✉️</div>
                Envoyer un email de test
            </div>
        </div>
        <div class="card-body">
            <div style="font-size:13px;color:var(--ink-muted);margin-bottom:18px;line-height:1.6;">
                Saisissez une adresse email accessible pour vérifier que le SMTP fonctionne.
                Le résultat sera aussi tracé dans <code>laravel.log</code>.
            </div>

            <form action="{{ route('diagnostic.mail.tester') }}" method="POST">
                @csrf
                <div class="form-group" style="margin-bottom:16px;">
                    <label for="email">Adresse email de test</label>
                    <input type="email" id="email" name="email"
                        value="{{ old('email', $testEmail) }}"
                        placeholder="votre@email.com" required>
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">🚀 Tester l'envoi</button>
            </form>
        </div>
    </div>
@endsection