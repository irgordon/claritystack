<?php
// tests/mock_smtp_server.php

$port = 2525;
$socket = stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr);

if (!$socket) {
    echo "Error creating server: $errstr ($errno)\n";
    exit(1);
}

echo "Mock SMTP Server listening on port $port\n";

// Write logs to a file for verification
$logFile = __DIR__ . '/mock_smtp.log';
file_put_contents($logFile, ""); // Clear log

while ($conn = stream_socket_accept($socket, -1)) {
    fwrite($conn, "220 Mock SMTP Service Ready\r\n");

    $state = 'INIT';
    // States: INIT, READY, MAIL_OK, RCPT_OK, DATA_MODE

    $dataMode = false;

    while ($line = fgets($conn)) {
        $cmd = trim($line);
        file_put_contents($logFile, "CMD: $cmd (State: $state)\n", FILE_APPEND);

        if ($state === 'DATA_MODE') {
            if ($cmd === '.') {
                $state = 'READY';
                fwrite($conn, "250 OK: Message accepted\r\n");
            }
            continue;
        }

        if (stripos($cmd, 'EHLO') === 0 || stripos($cmd, 'HELO') === 0) {
            $state = 'READY';
            fwrite($conn, "250-Hello\r\n250 AUTH LOGIN\r\n");
        } elseif (stripos($cmd, 'AUTH') === 0) {
             // Handle AUTH LOGIN sequence blindly
             fwrite($conn, "334 VXNlcm5hbWU6\r\n"); // Username:
             fgets($conn); // consume username
             fwrite($conn, "334 UGFzc3dvcmQ6\r\n"); // Password:
             fgets($conn); // consume password
             fwrite($conn, "235 Authentication successful\r\n");
             // State remains whatever it was (usually READY)
        } elseif (stripos($cmd, 'MAIL FROM') === 0) {
            if ($state !== 'READY') {
                fwrite($conn, "503 Sender already specified (State: $state)\r\n");
            } else {
                $state = 'MAIL_OK';
                fwrite($conn, "250 OK\r\n");
            }
        } elseif (stripos($cmd, 'RCPT TO') === 0) {
            // Can receive RCPT TO if we are in MAIL_OK or RCPT_OK
            if ($state !== 'MAIL_OK' && $state !== 'RCPT_OK') {
                fwrite($conn, "503 Bad sequence of commands (State: $state)\r\n");
            } else {
                if (strpos($cmd, 'fail@example.com') !== false) {
                    // Start of failure simulation
                    // Note: In real SMTP, a failed RCPT TO doesn't necessarily abort the transaction,
                    // but if the client tries to start a NEW mail without finishing this one, it's an error.
                    // If we return 550, the client (SmtpClient) throws an exception and stops sending this email.
                    // The client then catches the exception.
                    // If it tries to send the NEXT email, it calls MAIL FROM.
                    // But the state is still MAIL_OK (or RCPT_OK if previous RCPT worked).
                    // So next MAIL FROM will fail with 503.
                    fwrite($conn, "550 Recipient rejected\r\n");
                    // State remains MAIL_OK or RCPT_OK
                } else {
                    $state = 'RCPT_OK';
                    fwrite($conn, "250 OK\r\n");
                }
            }
        } elseif (stripos($cmd, 'DATA') === 0) {
            if ($state !== 'RCPT_OK') {
                 fwrite($conn, "503 Need RCPT (first) (State: $state)\r\n");
            } else {
                $state = 'DATA_MODE';
                fwrite($conn, "354 Start mail input; end with <CRLF>.<CRLF>\r\n");
            }
        } elseif (stripos($cmd, 'RSET') === 0) {
            $state = 'READY';
            fwrite($conn, "250 OK\r\n");
        } elseif (stripos($cmd, 'QUIT') === 0) {
            fwrite($conn, "221 Bye\r\n");
            fclose($conn);
            break;
        } else {
            // Default success for other things (e.g. NOOP)
             fwrite($conn, "250 OK\r\n");
        }
    }
}
fclose($socket);
