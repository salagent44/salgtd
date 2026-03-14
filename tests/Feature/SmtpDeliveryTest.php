<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tests that the SMTP server is running and accepts emails via the SMTP protocol.
 * The server writes to the production database (separate process), so we verify
 * protocol-level behavior here. Data flow is covered by InboundEmailTest.
 */
class SmtpDeliveryTest extends TestCase
{
    private function smtpRunning(): bool
    {
        $conn = @fsockopen('127.0.0.1', 25, $errno, $errstr, 2);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }

    private function smtpConnect()
    {
        $conn = fsockopen('127.0.0.1', 25, $errno, $errstr, 5);
        $this->assertNotFalse($conn, "Could not connect to SMTP server: {$errstr}");
        stream_set_timeout($conn, 10);
        return $conn;
    }

    private function readResponse($conn): string
    {
        $response = '';
        while (true) {
            $line = fgets($conn, 512);
            $this->assertNotFalse($line, 'SMTP server did not respond');
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
            if (strlen($line) < 4) break;
        }
        return $response;
    }

    private function assertResponse($conn, string $code): string
    {
        $response = $this->readResponse($conn);
        $this->assertStringStartsWith($code, $response, "Expected {$code}, got: " . trim($response));
        return $response;
    }

    public function test_smtp_server_sends_220_greeting(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $greeting = $this->readResponse($conn);
        $this->assertStringStartsWith('220', $greeting);
        $this->assertStringContainsString('ESMTP', $greeting);
        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }

    public function test_smtp_server_handles_ehlo(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "EHLO test.example.com\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }

    public function test_smtp_accepts_full_email_transaction(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "EHLO test.example.com\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "MAIL FROM:<sender@example.com>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "RCPT TO:<inbox@tasks.salmaster.dev>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "DATA\r\n");
        $this->assertResponse($conn, '354');

        $email = "From: Test Sender <sender@example.com>\r\n";
        $email .= "To: inbox@tasks.salmaster.dev\r\n";
        $email .= "Subject: SMTP protocol test\r\n";
        $email .= "Content-Type: text/plain\r\n";
        $email .= "\r\n";
        $email .= "This is a test email body.\r\n";
        $email .= ".\r\n";

        fwrite($conn, $email);
        $this->assertResponse($conn, '250');

        fwrite($conn, "QUIT\r\n");
        $this->assertResponse($conn, '221');
        fclose($conn);
    }

    public function test_smtp_handles_rset(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "EHLO test.example.com\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "MAIL FROM:<sender@example.com>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "RSET\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }

    public function test_smtp_handles_noop(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "NOOP\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }

    public function test_smtp_rejects_unknown_command(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "NONSENSE\r\n");
        $this->assertResponse($conn, '502');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }

    public function test_smtp_handles_multiple_recipients(): void
    {
        if (!$this->smtpRunning()) {
            $this->markTestSkipped('SMTP server not running');
        }

        $conn = $this->smtpConnect();
        $this->assertResponse($conn, '220');

        fwrite($conn, "EHLO test.example.com\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "MAIL FROM:<sender@example.com>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "RCPT TO:<inbox@tasks.salmaster.dev>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "RCPT TO:<other@tasks.salmaster.dev>\r\n");
        $this->assertResponse($conn, '250');

        fwrite($conn, "QUIT\r\n");
        fclose($conn);
    }
}
