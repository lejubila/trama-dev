<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\TopologySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $snapshot_date
 * @property string $image_path
 * @property array<string, mixed>|null $view_state
 * @property int|null $created_by
 */
class TopologySnapshot extends Model implements AuditableContract
{
    /** @use HasFactory<TopologySnapshotFactory> */
    use BelongsToTenant, HasFactory, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'snapshot_date',
        'image_path',
        'view_state',
        'created_by',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'view_state' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
