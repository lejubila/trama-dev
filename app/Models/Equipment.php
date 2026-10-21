<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfaceType;
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
 * @property bool $locked
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
        'host_equipment_id',
        'room_id',
        'position_x',
        'position_y',
        'name',
        'type',
        'vendor',
        'model',
        'serial',
        'firmware',
        'asset_tag',
        'mounted',
        'locked',
        'position_u_start',
        'position_u_height',
        'position_orient',
        'on_top',
        'hidden_in_topology',
        'status',
        'management_ip',
        'description',
        'custom_fields',
        'icon_path',
        'icon_size_px',
    ];

    protected function casts(): array
    {
        return [
            'type' => EquipmentType::class,
            'status' => EquipmentStatus::class,
            'mounted' => 'boolean',
            'locked' => 'boolean',
            'on_top' => 'boolean',
            'hidden_in_topology' => 'boolean',
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
     * Direct room reference for unracked equipment (and a redundant pointer
     * for racked ones — kept in sync at save time so a single column always
     * answers "in which room does this device live?").
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
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

    public function isVirtualMachine(): bool
    {
        return $this->type === EquipmentType::VirtualMachine;
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(self::class, 'host_equipment_id');
    }

    /**
     * @return HasMany<Equipment, $this>
     */
    public function virtualMachines(): HasMany
    {
        return $this->hasMany(self::class, 'host_equipment_id');
    }

    /**
     * Adds a minimal interface named after the first free "{prefix}N" (wlan0,
     * vpn1, ...), for clients attached without picking a port first.
     */
    public function addNextInterface(string $prefix, InterfaceType $type, InterfaceMedia $media): NetworkInterface
    {
        $taken = $this->interfaces()->pluck('name')->all();
        $i = 0;
        while (in_array($prefix.$i, $taken, true)) {
            $i++;
        }

        return $this->interfaces()->create([
            'tenant_id' => $this->tenant_id,
            'name' => $prefix.$i,
            'type' => $type,
            'media' => $media,
        ]);
    }
}
