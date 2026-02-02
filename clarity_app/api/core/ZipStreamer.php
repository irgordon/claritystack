<?php
namespace Core;

class ZipStreamer {
    private $outputStream;
    private $files = [];
    private $offset = 0;

    /**
     * @param resource|null $outputStream Optional stream to write to. Defaults to php://output.
     */
    public function __construct($outputStream = null) {
        if (is_resource($outputStream)) {
            $this->outputStream = $outputStream;
        } else {
            $this->outputStream = fopen('php://output', 'wb');
        }
    }

    /**
     * Adds a file to the ZIP from a stream.
     * Uses ZIP Data Descriptors (Bit 3) to stream without knowing size/CRC upfront.
     *
     * @param string $name Internal filename in the ZIP
     * @param resource $stream Source stream
     */
    public function addFileFromStream($name, $stream) {
        $name = str_replace('\\', '/', $name);

        // 1. Write Local File Header
        // Signature
        $header = pack('V', 0x04034b50);
        // Version needed (2.0)
        $header .= pack('v', 20);
        // Bit flag (Bit 3 = 0x08 for data descriptor)
        $header .= pack('v', 0x08);
        // Compression method (0 = Store)
        $header .= pack('v', 0);
        // Last mod time/date
        $dosTime = $this->getDosTime(time());
        $header .= pack('V', $dosTime);
        // CRC-32, Comp Size, Uncomp Size (0 because of Bit 3)
        $header .= pack('V', 0);
        $header .= pack('V', 0);
        $header .= pack('V', 0);
        // Filename Length
        $header .= pack('v', strlen($name));
        // Extra Field Length
        $header .= pack('v', 0);
        // Filename
        $header .= $name;

        fwrite($this->outputStream, $header);

        // Record offset for CD
        $localHeaderOffset = $this->offset;
        $this->offset += strlen($header);

        // 2. Stream Data and Calculate CRC/Size
        $size = 0;
        $hashCtx = hash_init('crc32b');

        while (!feof($stream)) {
            $chunk = fread($stream, 8192); // 8KB chunks
            if ($chunk === false || strlen($chunk) === 0) break;

            hash_update($hashCtx, $chunk);
            fwrite($this->outputStream, $chunk);
            $len = strlen($chunk);
            $size += $len;
            $this->offset += $len;
        }

        $crc = hexdec(hash_final($hashCtx));

        // 3. Write Data Descriptor
        // Signature (Optional but recommended)
        $dd = pack('V', 0x08074b50);
        // CRC-32
        $dd .= pack('V', $crc);
        // Compressed Size
        $dd .= pack('V', $size);
        // Uncompressed Size
        $dd .= pack('V', $size);

        fwrite($this->outputStream, $dd);
        $this->offset += strlen($dd);

        // 4. Store Info for Central Directory
        $this->files[] = [
            'name' => $name,
            'offset' => $localHeaderOffset,
            'size' => $size,
            'crc' => $crc,
            'dosTime' => $dosTime
        ];
    }

    /**
     * Writes the Central Directory and End of Central Directory Record.
     */
    public function finish() {
        $cdStart = $this->offset;

        foreach ($this->files as $file) {
            // Central Directory Header
            $cd = pack('V', 0x02014b50); // Signature
            $cd .= pack('v', 20); // Version made by
            $cd .= pack('v', 20); // Version needed
            $cd .= pack('v', 0x08); // Bit flag
            $cd .= pack('v', 0); // Compression method
            $cd .= pack('V', $file['dosTime']); // Mod time/date
            $cd .= pack('V', $file['crc']); // CRC32
            $cd .= pack('V', $file['size']); // Comp size
            $cd .= pack('V', $file['size']); // Uncomp size
            $cd .= pack('v', strlen($file['name'])); // Filename len
            $cd .= pack('v', 0); // Extra field len
            $cd .= pack('v', 0); // Comment len
            $cd .= pack('v', 0); // Disk number start
            $cd .= pack('v', 0); // Internal attrs
            $cd .= pack('V', 32); // External attrs (Archive bit set)
            $cd .= pack('V', $file['offset']); // Local header offset
            $cd .= $file['name'];

            fwrite($this->outputStream, $cd);
            $this->offset += strlen($cd);
        }

        $cdSize = $this->offset - $cdStart;

        // End of Central Directory Record
        $eocd = pack('V', 0x06054b50); // Signature
        $eocd .= pack('v', 0); // Disk number
        $eocd .= pack('v', 0); // Disk with CD
        $eocd .= pack('v', count($this->files)); // Disk entries
        $eocd .= pack('v', count($this->files)); // Total entries
        $eocd .= pack('V', $cdSize); // Size of CD
        $eocd .= pack('V', $cdStart); // Offset of CD
        $eocd .= pack('v', 0); // Comment len

        fwrite($this->outputStream, $eocd);

        // Flush output
        fflush($this->outputStream);
    }

    private function getDosTime($timestamp) {
        $time = getdate($timestamp);
        return (($time['year'] - 1980) << 25) |
               ($time['mon'] << 21) |
               ($time['mday'] << 16) |
               ($time['hours'] << 11) |
               ($time['minutes'] << 5) |
               ($time['seconds'] >> 1);
    }
}
