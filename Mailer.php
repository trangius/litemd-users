<?php

declare(strict_types=1);

namespace LiteMD\Plugins\Users;

use LiteMD\Config;

// ----------------------------------------------------------------------------
// Simple SMTP mailer. Sends HTML emails using stream_socket_client with
// support for TLS (STARTTLS), SSL, and plain connections with AUTH LOGIN.
// No external dependencies — reads SMTP config from plugins.users.smtp.
// ----------------------------------------------------------------------------
final class Mailer
{
    // ----------------------------------------------------------------------------
    // Send an HTML email using the SMTP settings from config.
    // Returns true on success, throws RuntimeException on failure.
    // ----------------------------------------------------------------------------
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $cfg = self::getSmtpConfig();

        if ($cfg['host'] === '' || $cfg['from_email'] === '') {
            throw new \RuntimeException('SMTP is not configured. Go to Advanced > Users to set it up.');
        }

        $encryption = strtolower($cfg['encryption']);
        $host = $cfg['host'];
        $port = (int) $cfg['port'];

        // For SSL, connect directly with ssl:// prefix
        $address = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $socket = @stream_socket_client($address, $errno, $errstr, 10);
        if (!$socket) {
            throw new \RuntimeException('Could not connect to SMTP server: ' . $errstr);
        }

        // Set a read/write timeout
        stream_set_timeout($socket, 10);

        try {
            // Read greeting
            self::expectCode($socket, 220);

            // EHLO
            self::command($socket, 'EHLO localhost', 250);

            // STARTTLS for TLS encryption
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                if (!$crypto) {
                    throw new \RuntimeException('STARTTLS failed.');
                }
                // Re-EHLO after TLS
                self::command($socket, 'EHLO localhost', 250);
            }

            // AUTH LOGIN
            if ($cfg['username'] !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($cfg['username']), 334);
                self::command($socket, base64_encode($cfg['password']), 235);
            }

            // Envelope
            self::command($socket, 'MAIL FROM:<' . $cfg['from_email'] . '>', 250);
            self::command($socket, 'RCPT TO:<' . $to . '>', 250);

            // DATA
            self::command($socket, 'DATA', 354);

            // Build message headers and body
            $fromHeader = $cfg['from_name'] !== ''
                ? '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?= <' . $cfg['from_email'] . '>'
                : $cfg['from_email'];
            $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $message = "From: {$fromHeader}\r\n"
                . "To: {$to}\r\n"
                . "Subject: {$subjectEncoded}\r\n"
                . "Date: " . date('r') . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "\r\n"
                . $htmlBody . "\r\n"
                . ".";
            self::command($socket, $message, 250);

            // QUIT
            self::command($socket, 'QUIT', 221);

        } finally {
            fclose($socket);
        }

        return true;
    }

    // ----------------------------------------------------------------------------
    // Read SMTP config from plugins.users.smtp with defaults.
    // ----------------------------------------------------------------------------
    private static function getSmtpConfig(): array
    {
        $usersConfig = Config::getPluginConfig('users', []);
        $smtp = $usersConfig['smtp'] ?? [];

        return [
            'host'       => (string) ($smtp['host'] ?? ''),
            'port'       => (int) ($smtp['port'] ?? 587),
            'encryption' => (string) ($smtp['encryption'] ?? 'tls'),
            'username'   => (string) ($smtp['username'] ?? ''),
            'password'   => (string) ($smtp['password'] ?? ''),
            'from_email' => (string) ($smtp['from_email'] ?? ''),
            'from_name'  => (string) ($smtp['from_name'] ?? ''),
        ];
    }

    // ----------------------------------------------------------------------------
    // Send a command and expect a specific response code.
    // ----------------------------------------------------------------------------
    private static function command($socket, string $cmd, int $expectedCode): string
    {
        fwrite($socket, $cmd . "\r\n");
        return self::expectCode($socket, $expectedCode);
    }

    // ----------------------------------------------------------------------------
    // Read response lines until we get a final response (no dash after code),
    // then verify the response code matches what we expect.
    // ----------------------------------------------------------------------------
    private static function expectCode($socket, int $expected): string
    {
        $fullResponse = '';

        // Read all response lines (multi-line responses have dash after code)
        do {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new \RuntimeException('SMTP connection lost.');
            }
            $fullResponse .= $line;
            $code = (int) substr($line, 0, 3);
            $continued = (isset($line[3]) && $line[3] === '-');
        } while ($continued);

        if ($code !== $expected) {
            throw new \RuntimeException('SMTP error: expected ' . $expected . ', got: ' . trim($fullResponse));
        }

        return $fullResponse;
    }
}
