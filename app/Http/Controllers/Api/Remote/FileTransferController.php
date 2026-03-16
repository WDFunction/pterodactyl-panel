<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\FileTransfer;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileTransferController extends Controller
{
    /**
     * Handles a callback from Wings updating the status of a file transfer.
     * Wings calls this after completing (or failing) an S3 pull/push operation.
     */
    public function updateStatus(Request $request, string $transfer): JsonResponse
    {
        /** @var \Pterodactyl\Models\Node $node */
        $node = $request->attributes->get('node');

        /** @var FileTransfer $model */
        $model = FileTransfer::query()
            ->where('uuid', $transfer)
            ->firstOrFail();

        /** @var \Pterodactyl\Models\Server $server */
        $server = $model->server;
        if ($server->node_id !== $node->id) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this transfer.');
        }

        if (in_array($model->status, [FileTransfer::STATUS_COMPLETED, FileTransfer::STATUS_FAILED])) {
            throw new BadRequestHttpException('Cannot update the status of a transfer that is already marked as completed or failed.');
        }

        $successful = $request->boolean('successful');

        $model->update([
            'status' => $successful ? FileTransfer::STATUS_COMPLETED : FileTransfer::STATUS_FAILED,
            'error' => $successful ? null : ($request->input('error') ?? 'Unknown error from Wings'),
            'completed_at' => now(),
        ]);

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
