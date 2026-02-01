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
        
        $content = fopen($sourceFile, 'rb');
        
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
        } finally {
            if (is_resource($content)) {
                fclose($content);
            }
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

    public function readStream(string $path) {
        $fileId = $this->findFileIdByName(basename($path));
        if (!$fileId) return null;

        $response = $this->service->files->get($fileId, ['alt' => 'media']);
        // The Google Client Library returns a Guzzle Stream, checking how to get resource
        // Assuming getBody() returns a streamable object, we can detach the resource.
        $body = $response->getBody();
        if (method_exists($body, 'detach')) {
            return $body->detach();
        }
        // Fallback if not streamable directly (or mock)
        return null;
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
