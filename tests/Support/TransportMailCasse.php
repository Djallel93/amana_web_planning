<?php
// tests/Support/TransportMailCasse.php
//
// Transport d'e-mail qui échoue toujours : sert à prouver qu'un envoi en panne ne fait jamais
// échouer l'action qui l'a déclenché. Aucun réseau — l'exception est levée localement.
//
//   TransportMailCasse::activer();   // le mailer par défaut devient « casse »

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class TransportMailCasse extends AbstractTransport
{
    public static function activer(): void
    {
        Mail::extend('casse', fn() => new self());
        config(['mail.default' => 'casse', 'mail.mailers.casse' => ['transport' => 'casse']]);
    }

    protected function doSend(SentMessage $message): void
    {
        throw new RuntimeException('SMTP indisponible');
    }

    public function __toString(): string
    {
        return 'casse://';
    }
}
