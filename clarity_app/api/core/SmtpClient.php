<?php

class SmtpClient {
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $debug = false;

    public function __construct($host, $port, $username = null, $password = null, $timeout = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    public function connect() {
        $connectionString = "tcp://{$this->host}:{$this->port}";
        // Simple SSL detection logic can be added here if needed, e.g. port 465
        if ($this->port == 465) {
            $connectionString = "ssl://{$this->host}:{$this->port}";
        }

        $this->socket = stream_socket_client($connectionString, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("SMTP Connection Failed: $errstr ($errno)");
        }

        $response = $this->readResponse();
        if (substr($response, 0, 3) != '220') {
            throw new Exception("SMTP Unexpected Banner: $response");
        }

        $this->command("EHLO " . gethostname());

        // Basic STARTTLS support for port 587
        if ($this->port == 587) {
            $this->command("STARTTLS");
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("SMTP TLS Negotiation Failed");
            }
            $this->command("EHLO " . gethostname());
        }

        if ($this->username && $this->password) {
            $this->authenticate();
        }
    }

    private function authenticate() {
        $this->command("AUTH LOGIN", 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    public function send($from, $to, $subject, $body, $headers = '') {
        if (!$this->socket) {
            $this->connect();
        }

        // Sanitize headers to prevent injection
        $from = $this->sanitizeHeader($from);
        $to = $this->sanitizeHeader($to);
        $subject = $this->sanitizeHeader($subject);

        // Reset state if necessary (RSET command is useful if a previous transaction failed)
        $this->command("RSET");
        $this->command("MAIL FROM: <$from>");
        $this->command("RCPT TO: <$to>");
        $this->command("DATA", 354);

        $data = "";
        if (!empty($headers)) {
            $data .= trim($headers) . "\r\n";
        }
        // Ensure subject is in headers if not provided
        if (stripos($headers, 'Subject:') === false) {
             $data .= "Subject: $subject\r\n";
        }
        // Ensure To/From are in headers
        if (stripos($headers, 'To:') === false) {
             $data .= "To: $to\r\n";
        }
        if (stripos($headers, 'From:') === false) {
             $data .= "From: $from\r\n";
        }

        $data .= "\r\n";

        // Normalize line endings and apply dot-stuffing
        $body = preg_replace('~\R~u', "\r\n", $body);
        if (substr($body, 0, 1) === '.') {
            $body = '.' . $body;
        }
        $body = str_replace("\r\n.", "\r\n..", $body);

        $data .= $body . "\r\n";
        $data .= ".";

        $this->command($data);
        return true;
    }

    private function sanitizeHeader($value) {
        return str_replace(["\r", "\n"], '', $value);
    }

    public function quit() {
        if ($this->socket) {
            try {
                fwrite($this->socket, "QUIT\r\n");
                fgets($this->socket, 512);
            } catch (Exception $e) {
                // Ignore errors during quit
            }
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function command($cmd, $expectCode = 250) {
        if (!$this->socket) {
            throw new Exception("SMTP Socket not connected");
        }

        if ($this->debug) {
            echo "> $cmd\n";
        }

        fwrite($this->socket, $cmd . "\r\n");
        $response = $this->readResponse();

        if ($this->debug) {
            echo "< $response\n";
        }

        // Check if response starts with expected code
        // Responses can be multi-line, we care about the code on the last line (or first line usually works for simple checks)
        // But usually the code is at the start.
        if (substr($response, 0, 3) != (string)$expectCode) {
             // Some commands might return different success codes, handle loosely if needed
             // For simplicity, we stick to strict check for now, except for EHLO which returns 250
             throw new Exception("SMTP Command '$cmd' failed: $response");
        }
    }

    private function readResponse() {
        $data = "";
        while ($line = fgets($this->socket, 512)) {
            $data .= $line;
            if (substr($line, 3, 1) == " ") {
                break;
            }
        }
        return $data;
    }
}
