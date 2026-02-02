<?php
require_once __DIR__ . '/../clarity_app/api/core/ZipStreamer.php';

use Core\ZipStreamer;

$tmpZip = tempnam(sys_get_temp_dir(), 'test_zip_');
$stream = fopen($tmpZip, 'wb');

$zip = new ZipStreamer($stream);

// File 1
$content1 = "Hello World";
$s1 = fopen('php://memory', 'r+');
fwrite($s1, $content1);
rewind($s1);
$zip->addFileFromStream('hello.txt', $s1);
fclose($s1);

// File 2
$content2 = str_repeat("Longer content ", 100);
$s2 = fopen('php://memory', 'r+');
fwrite($s2, $content2);
rewind($s2);
$zip->addFileFromStream('folder/test.txt', $s2);
fclose($s2);

$zip->finish();
fclose($stream);

echo "Zip created at: $tmpZip (" . filesize($tmpZip) . " bytes)\n";

// Verify
$reader = new ZipArchive();
if ($reader->open($tmpZip) === true) {
    echo "ZipArchive opened successfully.\n";

    // Check File 1
    $f1 = $reader->getFromName('hello.txt');
    if ($f1 === $content1) {
        echo "PASS: hello.txt content matches.\n";
    } else {
        echo "FAIL: hello.txt mismatch.\n";
        echo "Expected: " . substr($content1, 0, 50) . "...\n";
        echo "Got: " . substr($f1, 0, 50) . "...\n";
        exit(1);
    }

    // Check File 2
    $f2 = $reader->getFromName('folder/test.txt');
    if ($f2 === $content2) {
        echo "PASS: folder/test.txt content matches.\n";
    } else {
        echo "FAIL: folder/test.txt mismatch.\n";
        exit(1);
    }

    // Check count
    if ($reader->numFiles === 2) {
        echo "PASS: File count correct.\n";
    } else {
        echo "FAIL: Expected 2 files, got $reader->numFiles.\n";
        exit(1);
    }

    $reader->close();
} else {
    echo "FAIL: ZipArchive could not open the file.\n";
    exit(1);
}

@unlink($tmpZip);
echo "Verification Complete.\n";
?>
