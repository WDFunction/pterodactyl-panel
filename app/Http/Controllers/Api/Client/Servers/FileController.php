<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\FileTransfer;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Services\Files\S3FileTransferService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Transformers\Api\Client\FileObjectTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CopyFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\PullFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ListFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ChmodFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\RenameFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\S3DownloadRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CreateFolderRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\S3UploadUrlRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DecompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\GetFileContentsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\WriteFileContentRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\S3UploadCompleteRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\S3DownloadStatusRequest;

class FileController extends ClientApiController
{
    /**
     * FileController constructor.
     */
    public function __construct(
        private NodeJWTService $jwtService,
        private DaemonFileRepository $fileRepository,
        private S3FileTransferService $s3TransferService,
    ) {
        parent::__construct();
    }

    /**
     * Returns a listing of files in a given directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function directory(ListFilesRequest $request, Server $server): array
    {
        $contents = $this->fileRepository
            ->setServer($server)
            ->getDirectory($request->get('directory') ?? '/');

        return $this->fractal->collection($contents)
            ->transformWith($this->getTransformer(FileObjectTransformer::class))
            ->toArray();
    }

    /**
     * Return the contents of a specified file for the user.
     *
     * @throws \Throwable
     */
    public function contents(GetFileContentsRequest $request, Server $server): Response
    {
        $response = $this->fileRepository->setServer($server)->getContent(
            $request->get('file'),
            config('pterodactyl.files.max_edit_size')
        );

        Activity::event('server:file.read')->property('file', $request->get('file'))->log();

        return new Response($response, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    /**
     * Generates a one-time token with a link that the user can use to
     * download a given file.
     *
     * @throws \Throwable
     */
    public function download(GetFileContentsRequest $request, Server $server): array
    {
        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
            ->setUser($request->user())
            ->setClaims([
                'file_path' => rawurldecode($request->get('file')),
                'server_uuid' => $server->uuid,
            ])
            ->handle($server->node, $request->user()->id . $server->uuid);

        Activity::event('server:file.download')->property('file', $request->get('file'))->log();

        return [
            'object' => 'signed_url',
            'attributes' => [
                'url' => sprintf(
                    '%s/download/file?token=%s',
                    $server->node->getConnectionAddress(),
                    $token->toString()
                ),
            ],
        ];
    }

    /**
     * Writes the contents of the specified file to the server.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function write(WriteFileContentRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server)->putContent($request->get('file'), $request->getContent());

        Activity::event('server:file.write')->property('file', $request->get('file'))->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Creates a new folder on the server.
     *
     * @throws \Throwable
     */
    public function create(CreateFolderRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository
            ->setServer($server)
            ->createDirectory($request->input('name'), $request->input('root', '/'));

        Activity::event('server:file.create-directory')
            ->property('name', $request->input('name'))
            ->property('directory', $request->input('root'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Renames a file on the remote machine.
     *
     * @throws \Throwable
     */
    public function rename(RenameFileRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository
            ->setServer($server)
            ->renameFiles($request->input('root'), $request->input('files'));

        Activity::event('server:file.rename')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Copies a file on the server.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function copy(CopyFileRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository
            ->setServer($server)
            ->copyFile($request->input('location'));

        Activity::event('server:file.copy')->property('file', $request->input('location'))->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function compress(CompressFilesRequest $request, Server $server): array
    {
        $file = $this->fileRepository->setServer($server)->compressFiles(
            $request->input('root'),
            $request->input('files')
        );

        Activity::event('server:file.compress')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return $this->fractal->item($file)
            ->transformWith($this->getTransformer(FileObjectTransformer::class))
            ->toArray();
    }

    /**
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function decompress(DecompressFilesRequest $request, Server $server): JsonResponse
    {
        set_time_limit(300);

        $this->fileRepository->setServer($server)->decompressFile(
            $request->input('root'),
            $request->input('file')
        );

        Activity::event('server:file.decompress')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('file'))
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Deletes files or folders for the server in the given root directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function delete(DeleteFileRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server)->deleteFiles(
            $request->input('root'),
            $request->input('files')
        );

        Activity::event('server:file.delete')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Updates file permissions for file(s) in the given root directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function chmod(ChmodFilesRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server)->chmodFiles(
            $request->input('root'),
            $request->input('files')
        );

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Requests that a file be downloaded from a remote location by Wings.
     *
     * @throws \Throwable
     */
    public function pull(PullFileRequest $request, Server $server): JsonResponse
    {
        $this->fileRepository->setServer($server)->pull(
            $request->input('url'),
            $request->input('directory'),
            $request->safe(['filename', 'use_header', 'foreground'])
        );

        Activity::event('server:file.pull')
            ->property('directory', $request->input('directory'))
            ->property('url', $request->input('url'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    public function s3Enabled(): JsonResponse
    {
        return new JsonResponse([
            'enabled' => S3FileTransferService::isEnabled(),
            'icon_url' => config('file-transfer.icon_url', ''),
        ]);
    }

    /**
     * Generate a presigned S3 upload URL for the browser to upload a file directly to S3.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     */
    public function s3UploadUrl(S3UploadUrlRequest $request, Server $server): JsonResponse
    {
        if (!S3FileTransferService::isEnabled()) {
            return new JsonResponse(['error' => 'S3 file transfer is not enabled.'], Response::HTTP_BAD_REQUEST);
        }

        $filename = basename($request->input('filename'));
        $directory = $request->input('directory');
        $filePath = rtrim($directory, '/') . '/' . $filename;

        $s3Key = $this->s3TransferService->generateS3Key($server, $filename);
        $uploadUrl = $this->s3TransferService->createUploadUrl($s3Key);

        $transfer = $this->s3TransferService->createTransferRecord(
            $server,
            $request->user()->id,
            $s3Key,
            $filePath,
            FileTransfer::DIRECTION_UPLOAD
        );

        return new JsonResponse([
            'upload_url' => $uploadUrl,
            's3_key' => $s3Key,
            'transfer_id' => $transfer->uuid,
        ]);
    }

    /**
     * Handle notification that a file has been uploaded to S3. This will instruct
     * Wings to download the file from S3 to the server filesystem.
     *
     * @throws \Throwable
     */
    public function s3UploadComplete(S3UploadCompleteRequest $request, Server $server): JsonResponse
    {
        if (!S3FileTransferService::isEnabled()) {
            return new JsonResponse(['error' => 'S3 file transfer is not enabled.'], Response::HTTP_BAD_REQUEST);
        }

        $transfer = FileTransfer::query()
            ->where('uuid', $request->input('transfer_id'))
            ->where('server_id', $server->id)
            ->where('direction', FileTransfer::DIRECTION_UPLOAD)
            ->where('status', FileTransfer::STATUS_PENDING)
            ->firstOrFail();

        $downloadUrl = $this->s3TransferService->createDownloadUrl($transfer->s3_key);

        $filePath = $transfer->file_path;
        $directory = dirname($filePath);
        $filename = basename($filePath);

        if ($directory === '.') {
            $directory = '/';
        }

        $transfer->update(['status' => FileTransfer::STATUS_PROCESSING]);

        $this->fileRepository->setServer($server)->pullFromS3(
            $downloadUrl,
            $directory,
            $filename,
            $transfer->uuid
        );

        Activity::event('server:file.s3-upload')
            ->property('file', $filePath)
            ->log();

        return new JsonResponse([
            'status' => 'processing',
            'transfer_id' => $transfer->uuid,
        ]);
    }

    /**
     * Request that Wings upload a file to S3 for the user to download.
     * Returns a transfer_id that the frontend can poll for completion.
     *
     * @throws \Throwable
     */
    public function s3DownloadUrl(S3DownloadRequest $request, Server $server): JsonResponse
    {
        if (!S3FileTransferService::isEnabled()) {
            return new JsonResponse(['error' => 'S3 file transfer is not enabled.'], Response::HTTP_BAD_REQUEST);
        }

        $filePath = rawurldecode($request->get('file'));
        $filename = basename($filePath);

        $s3Key = $this->s3TransferService->generateS3Key($server, $filename);
        $uploadUrl = $this->s3TransferService->createUploadUrl($s3Key);

        $transfer = $this->s3TransferService->createTransferRecord(
            $server,
            $request->user()->id,
            $s3Key,
            $filePath,
            FileTransfer::DIRECTION_DOWNLOAD
        );

        $transfer->update(['status' => FileTransfer::STATUS_PROCESSING]);

        $this->fileRepository->setServer($server)->pushToS3(
            $filePath,
            $uploadUrl,
            $transfer->uuid
        );

        Activity::event('server:file.s3-download')
            ->property('file', $filePath)
            ->log();

        return new JsonResponse([
            'transfer_id' => $transfer->uuid,
        ]);
    }

    /**
     * Poll the status of an S3 download transfer. Returns the download URL when ready.
     */
    public function s3DownloadStatus(S3DownloadStatusRequest $request, Server $server): JsonResponse
    {
        $transferId = $request->get('transfer_id');

        $transfer = FileTransfer::query()
            ->where('uuid', $transferId)
            ->where('server_id', $server->id)
            ->where('direction', FileTransfer::DIRECTION_DOWNLOAD)
            ->firstOrFail();

        $response = [
            'status' => $transfer->status,
        ];

        if ($transfer->status === FileTransfer::STATUS_COMPLETED) {
            $response['download_url'] = $this->s3TransferService->getPublicUrl($transfer->s3_key);
        }

        if ($transfer->status === FileTransfer::STATUS_FAILED) {
            $response['error'] = $transfer->error ?? 'Unknown error';
        }

        return new JsonResponse($response);
    }
}
