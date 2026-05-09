<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $rack_id
 * @property string $name
 * @property EquipmentType|null $type
 * @property EquipmentStatus|null $status
 * @property bool $mounted
 * @property int|null $position_u_start
 * @property int|null $position_u_height
 * @property string|null $vendor
 * @property string|null $model
 */
class Equipment extends Model implements AuditableContract
{
    /** @use HasFactory<EquipmentFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'rack_id',
        'name',
        'type',
        'vendor',
        'model',
        'serial',
        'firmware',
        'asset_tag',
        'mounted',
        'position_u_start',
        'position_u_height',
        'position_orient',
        'status',
        'management_ip',
        'description',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'type' => EquipmentType::class,
            'status' => EquipmentStatus::class,
            'mounted' => 'boolean',
            'custom_fields' => 'array',
            'position_u_start' => 'integer',
            'position_u_height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Rack, $this>
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * @return HasMany<NetworkInterface, $this>
     */
    public function interfaces(): HasMany
    {
        return $this->hasMany(NetworkInterface::class);
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function isRackMounted(): bool
    {
        return $this->mounted && $this->rack_id !== null;
    }

    /**
     * @return list<int>|null
     */
    public function rackUnitsRange(): ?array
    {
        if (! $this->isRackMounted() || $this->position_u_start === null || $this->position_u_height === null) {
            return null;
        }

        return range($this->position_u_start, $this->position_u_start + $this->position_u_height - 1);
    }
}
