<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $server_id
 * @property int $user_id
 * @property string $s3_key
 * @property string $file_path
 * @property string $direction
 * @property string $status
 * @property string|null $error
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property \Pterodactyl\Models\Server $server
 * @property \Pterodactyl\Models\User $user
 */
class FileTransfer extends Model
{
    public const DIRECTION_UPLOAD = 'upload';
    public const DIRECTION_DOWNLOAD = 'download';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'file_transfers';

    protected bool $immutableDates = true;

    protected $casts = [
        'id' => 'int',
        'server_id' => 'int',
        'user_id' => 'int',
        'completed_at' => 'datetime',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static array $validationRules = [
        'uuid' => 'required|uuid',
        'server_id' => 'required|integer|exists:servers,id',
        'user_id' => 'required|integer|exists:users,id',
        's3_key' => 'required|string',
        'file_path' => 'required|string',
        'direction' => 'required|string|in:upload,download',
        'status' => 'required|string|in:pending,processing,completed,failed',
        'error' => 'nullable|string',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
