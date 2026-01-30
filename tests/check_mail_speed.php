<?php
$start = microtime(true);
$result = @mail('test@example.com', 'Test Subject', 'Test Body');
$end = microtime(true);
echo "Mail result: " . ($result ? 'true' : 'false') . "\n";
echo "Time taken: " . ($end - $start) . " seconds\n";
