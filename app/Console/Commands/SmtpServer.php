<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SmtpServer extends Command
{
    protected $signature = 'smtp:serve {--port=25} {--hostname=localhost}';
    protected $description = 'Run a lightweight SMTP server that receives emails into the GTD inbox';

    private $socket;

    public function handle(): int
    {
        $port = $this->option('port');
        $hostname = $this->option('hostname');

        $this->socket = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr);
        if (!$this->socket) {
            $this->error("Failed to listen on port {$port}: {$errstr}");
            return 1;
        }

        $this->info("SMTP server listening on :{$port}");

        while (true) {
            $conn = @stream_socket_accept($this->socket, -1);
            if (!$conn) continue;

            try {
                $this->handleConnection($conn, $hostname);
            } catch (\Throwable $e) {
                $this->warn("Connection error: {$e->getMessage()}");
            } finally {
                @fclose($conn);
            }
        }
    }

    private function handleConnection($conn, string $hostname): void
    {
        stream_set_timeout($conn, 60);

        $this->send($conn, "220 {$hostname} ESMTP");

        $mailFrom = '';
        $rcptTo = [];
        $data = null;

        while (!feof($conn)) {
            $line = fgets($conn);
            if ($line === false) break;

            $line = rtrim($line, "\r\n");
            $upper = strtoupper($line);

            if (str_starts_with($upper, 'EHLO') || str_starts_with($upper, 'HELO')) {
                $this->send($conn, "250-{$hostname}");
                $this->send($conn, "250-SIZE 10485760");
                $this->send($conn, "250 OK");
            } elseif (str_starts_with($upper, 'MAIL FROM:')) {
                $mailFrom = $this->extractAddress(substr($line, 10));
                $this->send($conn, "250 OK");
            } elseif (str_starts_with($upper, 'RCPT TO:')) {
                $rcptTo[] = $this->extractAddress(substr($line, 8));
                $this->send($conn, "250 OK");
            } elseif ($upper === 'DATA') {
                $this->send($conn, "354 Start mail input; end with <CRLF>.<CRLF>");
                $data = $this->readData($conn);
                $this->send($conn, "250 OK");

                $this->processEmail($mailFrom, $rcptTo, $data);
                $mailFrom = '';
                $rcptTo = [];
                $data = null;
            } elseif ($upper === 'QUIT') {
                $this->send($conn, "221 Bye");
                return;
            } elseif ($upper === 'RSET') {
                $mailFrom = '';
                $rcptTo = [];
                $data = null;
                $this->send($conn, "250 OK");
            } elseif ($upper === 'NOOP') {
                $this->send($conn, "250 OK");
            } else {
                $this->send($conn, "502 Command not implemented");
            }
        }
    }

    private function send($conn, string $msg): void
    {
        fwrite($conn, $msg . "\r\n");
    }

    private function extractAddress(string $s): string
    {
        $s = trim($s);
        if (preg_match('/<([^>]+)>/', $s, $m)) {
            return $m[1];
        }
        return $s;
    }

    private function readData($conn): string
    {
        $buf = '';
        while (!feof($conn)) {
            $line = fgets($conn);
            if ($line === false) break;

            $trimmed = rtrim($line, "\r\n");
            if ($trimmed === '.') break;

            // Undo dot-stuffing
            if (str_starts_with($line, '..')) {
                $line = substr($line, 1);
            }
            $buf .= $line;
        }
        return $buf;
    }

    private function processEmail(string $envelopeFrom, array $rcptTo, string $rawData): void
    {
        [$headers, $body] = $this->parseEmail($rawData);

        $subject = $this->decodeHeader($headers['subject'] ?? '(no subject)');
        $messageId = $headers['message-id'] ?? null;

        $fromAddr = $envelopeFrom;
        $fromName = null;
        if (!empty($headers['from'])) {
            [$fromAddr, $fromName] = $this->parseFromHeader($headers['from']);
            if (!$fromAddr) $fromAddr = $envelopeFrom;
        }

        $toAddr = $rcptTo[0] ?? '';

        // Dedup by message_id
        if ($messageId) {
            $existing = Email::where('message_id', $messageId)->first();
            if ($existing) {
                $this->line("Duplicate email skipped: {$messageId}");
                return;
            }
        }

        $item = Item::create([
            'id' => Str::ulid(),
            'title' => $subject,
            'status' => 'inbox',
        ]);

        Email::create([
            'id' => Str::ulid(),
            'item_id' => $item->id,
            'from_address' => $fromAddr,
            'from_name' => $fromName,
            'to_address' => $toAddr,
            'subject' => $subject,
            'body_text' => $body,
            'received_at' => now(),
            'message_id' => $messageId,
        ]);

        $this->info("Delivered email from={$fromAddr} subject=\"{$subject}\"");
    }

    private function parseEmail(string $raw): array
    {
        $headers = [];

        // Split headers and body
        $headerPart = '';
        $bodyPart = '';
        foreach (["\r\n\r\n", "\n\n"] as $sep) {
            $pos = strpos($raw, $sep);
            if ($pos !== false) {
                $headerPart = substr($raw, 0, $pos);
                $bodyPart = substr($raw, $pos + strlen($sep));
                break;
            }
        }
        if ($headerPart === '') {
            return [$headers, $raw];
        }

        // Unfold headers
        $headerPart = str_replace("\r\n", "\n", $headerPart);
        $lines = explode("\n", $headerPart);
        $unfolded = [];
        foreach ($lines as $line) {
            if (strlen($line) > 0 && ($line[0] === ' ' || $line[0] === "\t") && count($unfolded) > 0) {
                $unfolded[count($unfolded) - 1] .= ' ' . trim($line);
            } else {
                $unfolded[] = $line;
            }
        }

        foreach ($unfolded as $line) {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $key = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $headers[$key] = $value;
            }
        }

        // Extract plain text from MIME if needed
        $contentType = $headers['content-type'] ?? '';
        $body = $this->extractPlainText($bodyPart, $contentType);

        return [$headers, $body];
    }

    private function extractPlainText(string $body, string $contentType): string
    {
        if ($contentType === '') {
            return trim($body);
        }

        // Parse content type
        $parts = explode(';', $contentType, 2);
        $mediaType = strtolower(trim($parts[0]));

        if ($mediaType === 'text/plain') {
            return trim($body);
        }

        if (str_starts_with($mediaType, 'multipart/')) {
            $boundary = null;
            if (preg_match('/boundary=["\']?([^"\';\s]+)["\']?/i', $contentType, $m)) {
                $boundary = $m[1];
            }
            if (!$boundary) {
                return trim($body);
            }

            $sections = explode("--{$boundary}", $body);
            foreach ($sections as $section) {
                $section = ltrim($section, "\r\n");
                if (str_starts_with($section, '--')) continue; // closing boundary
                if (trim($section) === '') continue;

                // Split section headers and body
                $sectionBody = '';
                $sectionHeaders = '';
                foreach (["\r\n\r\n", "\n\n"] as $sep) {
                    $pos = strpos($section, $sep);
                    if ($pos !== false) {
                        $sectionHeaders = substr($section, 0, $pos);
                        $sectionBody = substr($section, $pos + strlen($sep));
                        break;
                    }
                }

                $sectionCT = '';
                if (preg_match('/^content-type:\s*(.+)$/mi', $sectionHeaders, $m)) {
                    $sectionCT = trim($m[1]);
                }

                $sectionMedia = strtolower(trim(explode(';', $sectionCT, 2)[0]));

                if ($sectionMedia === 'text/plain' || $sectionCT === '') {
                    $result = trim($sectionBody);
                    if ($result !== '') return $result;
                }

                if (str_starts_with($sectionMedia, 'multipart/')) {
                    $result = $this->extractPlainText($sectionBody, $sectionCT);
                    if ($result !== '') return $result;
                }
            }

            return trim($body);
        }

        return trim($body);
    }

    private function parseFromHeader(string $from): array
    {
        $from = trim($from);
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $from, $m)) {
            $name = trim($m[1], " \"'");
            $name = $this->decodeHeader($name);
            return [$m[2], $name];
        }
        return [$from, null];
    }

    private function decodeHeader(string $s): string
    {
        if (str_contains($s, '=?')) {
            $decoded = iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) return $decoded;
        }
        return $s;
    }
}
