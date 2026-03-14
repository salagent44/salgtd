<?php

namespace Tests\Feature;

use App\Console\Commands\SmtpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmtpMimeTest extends TestCase
{
    use RefreshDatabase;

    private function extractPlainText(string $body, string $contentType, string $headers = ''): string
    {
        $server = new SmtpServer();
        $method = new \ReflectionMethod($server, 'extractPlainText');
        $method->setAccessible(true);
        return $method->invoke($server, $body, $contentType, $headers);
    }

    public function test_plain_text_passthrough(): void
    {
        $result = $this->extractPlainText('Hello world', 'text/plain');
        $this->assertEquals('Hello world', $result);
    }

    public function test_base64_encoded_plain_text(): void
    {
        $encoded = base64_encode('Hello from base64');
        $headers = "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64";
        $result = $this->extractPlainText($encoded, 'text/plain; charset=utf-8', $headers);
        $this->assertEquals('Hello from base64', $result);
    }

    public function test_quoted_printable_encoded_plain_text(): void
    {
        $encoded = "Hello=20from=20QP=0D=0ASecond line";
        $headers = "Content-Type: text/plain\r\nContent-Transfer-Encoding: quoted-printable";
        $result = $this->extractPlainText($encoded, 'text/plain', $headers);
        $this->assertEquals("Hello from QP\r\nSecond line", $result);
    }

    public function test_multipart_with_base64_text_part(): void
    {
        $textBody = base64_encode('Decoded email body');
        $boundary = 'boundary123';
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "\r\n"
            . "{$textBody}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=utf-8\r\n"
            . "\r\n"
            . "<p>HTML version</p>\r\n"
            . "--{$boundary}--\r\n";

        $contentType = "multipart/alternative; boundary={$boundary}";
        $result = $this->extractPlainText($body, $contentType);
        $this->assertEquals('Decoded email body', $result);
    }

    public function test_multipart_with_qp_text_part(): void
    {
        $boundary = 'qpbound';
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n"
            . "\r\n"
            . "Hello=20World\r\n"
            . "--{$boundary}--\r\n";

        $contentType = "multipart/alternative; boundary={$boundary}";
        $result = $this->extractPlainText($body, $contentType);
        $this->assertEquals('Hello World', $result);
    }

    public function test_multipart_with_7bit_text_part(): void
    {
        $boundary = 'simple';
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Transfer-Encoding: 7bit\r\n"
            . "\r\n"
            . "Plain 7bit text\r\n"
            . "--{$boundary}--\r\n";

        $contentType = "multipart/alternative; boundary={$boundary}";
        $result = $this->extractPlainText($body, $contentType);
        $this->assertEquals('Plain 7bit text', $result);
    }

    public function test_no_content_type_returns_body_as_is(): void
    {
        $result = $this->extractPlainText('Just text', '');
        $this->assertEquals('Just text', $result);
    }
}
