<?php
namespace Core\Storage;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveAdapter implements StorageInterface {
    private $service;
    private $folderId;

    public function __construct(array $config) {
        $client = new Client();
        $client->setAuthConfig($config['service_account_json_path']);
        $client->addScope(Drive::DRIVE);
        $this->service = new Drive($client);
        $this->folderId = $config['root_folder_id'];
    }

    public function put(string $sourceFile, string $destinationPath): bool {
        $fileMetadata = new DriveFile([
            'name' => basename($destinationPath),
            'parents' => [$this->folderId] 
            // Note: Does not support subfolders dynamically in this simple example
        ]);
        
        $content = file_get_contents($sourceFile);
        
        try {
            $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => mime_content_type($sourceFile),
                'uploadType' => 'multipart'
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Drive Upload Error: " . $e->getMessage());
            return false;
        }
    }

    public function get(string $path): ?string {
        // Drive requires resolving Path -> ID. 
        // We assume $path IS the ID for Drive, or we must implement a lookup.
        $fileId = $this->findFileIdByName(basename($path));
        if (!$fileId) return null;

        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        return $response->getBody()->getContents();
    }

    public function delete(string $path): bool {
        $fileId = $this->findFileIdByName(basename($path));
        if ($fileId) {
            $this->service->files->delete($fileId);
            return true;
        }
        return false;
    }

    public function getUrl(string $path): string {
        // Google Drive images are hard to hotlink directly. 
        // Usually, you stream them via your own PHP proxy.
        return "/api/files/view/" . basename($path);
    }

    private function findFileIdByName($name) {
        $optParams = [
            'q' => "name = '$name' and '{$this->folderId}' in parents and trashed = false",
            'fields' => 'files(id, name)'
        ];
        $results = $this->service->files->listFiles($optParams);
        if (count($results->getFiles()) == 0) return null;
        return $results->getFiles()[0]->getId();
    }
}
