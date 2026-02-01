<?php
// tests/mock_smtp_server.php

$port = 2525;
$socket = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);

if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

echo "Mock SMTP Server running on port $port...\n";

while ($conn = stream_socket_accept($socket)) {
    // Simple handler per connection
    fwrite($conn, "220 MockSMTP Service Ready\r\n");

    $dataMode = false;

    while ($line = fgets($conn)) {
        $cmd = strtoupper(trim($line));

        if ($dataMode) {
            if (trim($line) === ".") {
                $dataMode = false;
                fwrite($conn, "250 OK Message accepted\r\n");
            }
            continue;
        }

        if (strpos($cmd, 'EHLO') === 0 || strpos($cmd, 'HELO') === 0) {
            fwrite($conn, "250-Hello\r\n250 AUTH LOGIN\r\n");
        } elseif (strpos($cmd, 'AUTH LOGIN') === 0) {
            fwrite($conn, "334 VXNlcm5hbWU6\r\n"); // Username:
            fgets($conn); // Read user
            fwrite($conn, "334 UGFzc3dvcmQ6\r\n"); // Password:
            fgets($conn); // Read pass
            fwrite($conn, "235 Authentication successful\r\n");
        } elseif (strpos($cmd, 'MAIL FROM') === 0) {
            fwrite($conn, "250 OK\r\n");
        } elseif (strpos($cmd, 'RCPT TO') === 0) {
            fwrite($conn, "250 OK\r\n");
        } elseif (strpos($cmd, 'DATA') === 0) {
            $dataMode = true;
            fwrite($conn, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
        } elseif (strpos($cmd, 'QUIT') === 0) {
            fwrite($conn, "221 Byte\r\n");
            fclose($conn);
            break;
        } else {
            fwrite($conn, "500 Command not recognized\r\n");
        }
    }
}
