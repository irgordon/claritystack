<?php
namespace Core\Storage;

require_once __DIR__ . '/LocalAdapter.php';

class StorageFactory {
    public static function create(array $config): StorageInterface {
        $driver = $config['STORAGE_DRIVER'] ?? 'local';

        switch ($driver) {
            case 'cloudinary':
                return new CloudinaryAdapter([
                    'cloud_name' => $config['CLOUDINARY_NAME'],
                    'api_key'    => $config['CLOUDINARY_KEY'],
                    'api_secret' => $config['CLOUDINARY_SECRET'],
                ]);
            
            case 'imagekit':
                return new ImagekitAdapter([
                    'public_key'   => $config['IMAGEKIT_PUBLIC'],
                    'private_key'  => $config['IMAGEKIT_PRIVATE'],
                    'url_endpoint' => $config['IMAGEKIT_URL'],
                ]);

            case 'drive':
                return new GoogleDriveAdapter([
                    'service_account_json_path' => __DIR__ . '/../../config/service-account.json',
                    'root_folder_id' => $config['DRIVE_ROOT_FOLDER'],
                ]);

            case 's3':
                // (Existing S3 Logic)
                return new S3Adapter($config);

            case 'local':
            default:
                $path = $config['STORAGE_PATH'] ?? __DIR__ . '/../../../../storage_secure';
                $localConfig = [
                    'sendfile_header' => $config['SENDFILE_HEADER'] ?? null,
                    'sendfile_prefix' => $config['SENDFILE_PREFIX'] ?? null,
                ];
                return new LocalAdapter($path, $localConfig);
        }
    }
}
