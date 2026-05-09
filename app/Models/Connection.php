<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $from_interface_id
 * @property int $to_interface_id
 * @property string $cable_type
 * @property string|null $cable_label
 * @property ConnectionStatus|null $status
 * @property Carbon|null $established_at
 */
class Connection extends Model implements AuditableContract
{
    /** @use HasFactory<ConnectionFactory> */
    use BelongsToTenant, HasFactory, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'from_interface_id',
        'to_interface_id',
        'cable_type',
        'cable_length_m',
        'cable_label',
        'color',
        'status',
        'notes',
        'established_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'cable_length_m' => 'decimal:2',
            'established_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function fromInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'from_interface_id');
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function toInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'to_interface_id');
    }

    /**
     * @return BelongsToMany<LinkGroup, $this>
     */
    public function linkGroups(): BelongsToMany
    {
        return $this->belongsToMany(LinkGroup::class, 'link_group_connection');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * The "other side" of the cable when given one of its endpoints.
     */
    public function otherEndpoint(NetworkInterface $endpoint): ?NetworkInterface
    {
        if ($endpoint->getKey() === $this->from_interface_id) {
            return $this->toInterface;
        }
        if ($endpoint->getKey() === $this->to_interface_id) {
            return $this->fromInterface;
        }

        return null;
    }
}
