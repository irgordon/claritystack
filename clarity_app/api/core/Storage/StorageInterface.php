<?php
namespace Core\Storage;

interface StorageInterface {
    public function put(string $sourceFile, string $destinationPath): bool;
    public function get(string $path): ?string;
    public function delete(string $path): bool;
    public function getUrl(string $path): string;

    /**
     * Returns a read-only stream resource for the file.
     * @return resource|null
     */
    public function readStream(string $path);
}
