<?php

namespace Pterodactyl\Services\Files;

use Aws\S3\S3Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\FileTransfer;

class S3FileTransferService
{
    protected S3Client $client;
    protected string $bucket;

    public function __construct()
    {
        $diskConfig = config('file-transfer.disk');

        $config = [
            'version' => 'latest',
            'region' => $diskConfig['region'],
        ];

        if (!empty($diskConfig['endpoint'])) {
            $config['endpoint'] = $diskConfig['endpoint'];
        }

        if (!empty($diskConfig['use_path_style_endpoint'])) {
            $config['use_path_style_endpoint'] = (bool) $diskConfig['use_path_style_endpoint'];
        }

        if (!empty($diskConfig['key']) && !empty($diskConfig['secret'])) {
            $config['credentials'] = [
                'key' => $diskConfig['key'],
                'secret' => $diskConfig['secret'],
            ];
        }

        $this->client = new S3Client($config);
        $this->bucket = $diskConfig['bucket'];
    }

    public static function isEnabled(): bool
    {
        return (bool) config('file-transfer.enabled', false);
    }

    /**
     * Generate a unique S3 object key for a file transfer.
     */
    public function generateS3Key(Server $server, string $filename): string
    {
        $prefix = config('file-transfer.prefix', 'file-transfers');

        return sprintf('%s/%s/%s/%s', $prefix, $server->uuid, Str::uuid(), $filename);
    }

    /**
     * Generate a presigned PUT URL for uploading a file to S3.
     */
    public function createUploadUrl(string $s3Key): string
    {
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
        ]);

        $lifespan = (int) config('file-transfer.presigned_url_lifespan', 60);
        $request = $this->client->createPresignedRequest(
            $cmd,
            CarbonImmutable::now()->addMinutes($lifespan)
        );

        return (string) $request->getUri();
    }

    /**
     * Generate a presigned GET URL for downloading a file from S3.
     */
    public function createDownloadUrl(string $s3Key): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
        ]);

        $lifespan = (int) config('file-transfer.presigned_url_lifespan', 60);
        $request = $this->client->createPresignedRequest(
            $cmd,
            CarbonImmutable::now()->addMinutes($lifespan)
        );

        return (string) $request->getUri();
    }

    /**
     * Generate a public URL for the given S3 key using the configured public base URL.
     * Falls back to a presigned GET URL if no public base URL is configured.
     */
    public function getPublicUrl(string $s3Key): string
    {
        $publicBaseUrl = config('file-transfer.public_base_url');

        if (!empty($publicBaseUrl)) {
            return rtrim($publicBaseUrl, '/') . '/' . ltrim($s3Key, '/');
        }

        return $this->createDownloadUrl($s3Key);
    }

    public function createTransferRecord(
        Server $server,
        int $userId,
        string $s3Key,
        string $filePath,
        string $direction
    ): FileTransfer {
        return FileTransfer::query()->create([
            'uuid' => Str::uuid()->toString(),
            'server_id' => $server->id,
            'user_id' => $userId,
            's3_key' => $s3Key,
            'file_path' => $filePath,
            'direction' => $direction,
            'status' => FileTransfer::STATUS_PENDING,
        ]);
    }
}
