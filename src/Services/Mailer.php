<?php
namespace Rongie\QuickHire\Services;

use Exception;

class Mailer
{
    public function __construct(private array $config = [])
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $fromEmail = (string)($this->config['from_email'] ?? 'no-reply@quickhire.local');
        $fromName = (string)($this->config['from_name'] ?? 'QuickHire');

        if (!empty($this->config['enabled'])) {
            $this->sendSmtp($to, $subject, $body, $fromEmail, $fromName);
            return true;
        }

        $headers = [
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . $fromEmail,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    private function sendSmtp(string $to, string $subject, string $body, string $fromEmail, string $fromName): void
    {
        $host = (string)($this->config['host'] ?? '');
        $port = (int)($this->config['port'] ?? 587);
        $username = (string)($this->config['username'] ?? '');
        $password = (string)($this->config['password'] ?? '');
        $encryption = strtolower((string)($this->config['encryption'] ?? 'tls'));

        if ($host === '' || $username === '' || $password === '') {
            throw new Exception('SMTP mail is enabled but host, username, or password is missing.');
        }

        $remote = $encryption === 'ssl'
            ? "ssl://{$host}:{$port}"
            : "tcp://{$host}:{$port}";

        $socket = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: {$errstr}");
        }

        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $this->command($socket, "EHLO {$serverName}", [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception('Could not start SMTP TLS encryption.');
                }
                $this->command($socket, "EHLO {$serverName}", [250]);
            }

            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($username), [334]);
            $this->command($socket, base64_encode($password), [235]);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . $this->formatAddress($fromEmail, $fromName),
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            $this->command($socket, $message, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expected);
    }

    private function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new Exception('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        return sprintf('"%s" <%s>', addcslashes($name, '"\\'), $email);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
