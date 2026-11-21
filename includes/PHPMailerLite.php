<?php
/**
 * CampusFix - SMTP Email Client (PHPMailer-Compatible Engine)
 * Supports live TLS/SSL SMTP socket delivery and transparent, auditable mock logging.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/mail.php';

class PHPMailerLite
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private bool $mockMode;

    public function __construct()
    {
        $this->host        = SMTP_HOST;
        $this->port        = SMTP_PORT;
        $this->user        = SMTP_USER;
        $this->pass        = SMTP_PASS;
        $this->encryption  = strtolower(SMTP_ENCRYPTION);
        $this->fromAddress = MAIL_FROM_ADDRESS;
        $this->fromName    = MAIL_FROM_NAME;
        $this->mockMode    = (MAIL_MOCK_MODE === 1 || empty($this->pass));
    }

    /**
     * Send an HTML formatted email
     *
     * @param string $to Recipient email address
     * @param string $subject Subject line
     * @param string $htmlBody HTML content
     * @return array ['success' => bool, 'mock' => bool, 'message' => string]
     */
    public function send(string $to, string $subject, string $htmlBody): array
    {
        // 1. If in Mock Mode (or credentials absent), log payload cleanly to file
        if ($this->mockMode) {
            $logEntry = sprintf(
                "[%s] [MOCK EMAIL DISPATCH]\nTO: %s\nFROM: %s <%s>\nSUBJECT: %s\nBODY:\n%s\n%s\n\n",
                date('Y-m-d H:i:s'),
                $to,
                $this->fromName,
                $this->fromAddress,
                $subject,
                strip_tags($htmlBody),
                str_repeat('-', 70)
            );

            $logPath = ROOT_PATH . 'logs/mail_mock.log';
            @file_put_contents($logPath, $logEntry, FILE_APPEND);

            return [
                'success' => true,
                'mock'    => true,
                'message' => 'Simulated email logged to logs/mail_mock.log (Mock Mode active).'
            ];
        }

        // 2. Live SMTP Socket Connection
        try {
            $prefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
            $socket = @fsockopen($prefix . $this->host, $this->port, $errno, $errstr, 10);

            if (!$socket) {
                throw new Exception("Could not connect to SMTP server {$this->host}:{$this->port} ({$errstr})");
            }

            $this->readResponse($socket);

            $this->sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            }

            if (!empty($this->user) && !empty($this->pass)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($this->user));
                $this->sendCommand($socket, base64_encode($this->pass));
            }

            $this->sendCommand($socket, "MAIL FROM: <{$this->fromAddress}>");
            $this->sendCommand($socket, "RCPT TO: <{$to}>");
            $this->sendCommand($socket, "DATA");

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromAddress}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";

            $messageData = $headers . $htmlBody . "\r\n.\r\n";
            fputs($socket, $messageData);
            $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return [
                'success' => true,
                'mock'    => false,
                'message' => "Live email successfully delivered to {$to} via {$this->host}."
            ];

        } catch (Exception $e) {
            error_log("PHPMailerLite SMTP Exception: " . $e->getMessage());
            return [
                'success' => false,
                'mock'    => false,
                'message' => 'SMTP Transport Error: ' . $e->getMessage()
            ];
        }
    }

    private function sendCommand($socket, string $cmd): string
    {
        fputs($socket, $cmd . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
}

