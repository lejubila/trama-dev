<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceSide;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\NetworkInterfaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Eloquent model for `interfaces` rows. Named NetworkInterface to avoid
 * clashing with the PHP `interface` keyword.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $equipment_id
 * @property string $name
 * @property InterfaceType|null $type
 * @property InterfaceMedia|null $media
 * @property InterfaceVlanMode|null $vlan_mode
 * @property InterfaceStatus|null $status
 * @property InterfacePoe|null $poe
 * @property int|null $speed_mbps
 * @property int|null $vlan_default
 */
class NetworkInterface extends Model implements AuditableContract
{
    /** @use HasFactory<NetworkInterfaceFactory> */
    use BelongsToTenant, HasFactory, TenantAuditable;

    protected $table = 'interfaces';

    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'name',
        'type',
        'side',
        'paired_interface_id',
        'backed_by_interface_id',
        'index',
        'speed_mbps',
        'media',
        'connector',
        'vlan_mode',
        'vlan_default',
        'vlans_allowed',
        'ip_address',
        'mac_address',
        'status',
        'poe',
        'description',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'type' => InterfaceType::class,
            'side' => InterfaceSide::class,
            'media' => InterfaceMedia::class,
            'vlan_mode' => InterfaceVlanMode::class,
            'status' => InterfaceStatus::class,
            'poe' => InterfacePoe::class,
            'index' => 'integer',
            'speed_mbps' => 'integer',
            'vlan_default' => 'integer',
            'vlans_allowed' => 'array',
            'custom_fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Connections where this interface is the "from" endpoint.
     *
     * @return HasMany<Connection, $this>
     */
    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'from_interface_id');
    }

    /**
     * Connections where this interface is the "to" endpoint.
     *
     * @return HasMany<Connection, $this>
     */
    public function incomingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'to_interface_id');
    }

    /**
     * The matching half of a keystone port. Always null for non-keystone
     * interfaces; for keystone interfaces it points to the opposite side
     * (front ↔ rear) created together via CreateKeystonePair.
     *
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function paired(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_interface_id');
    }

    /**
     * For vNIC interfaces on a virtual_machine equipment: points to the
     * physical interface of the hypervisor host that carries this vNIC's
     * traffic. Many vNICs can share the same backing pNIC (N:1) — that's
     * why no unique constraint exists on the column.
     *
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function backedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backed_by_interface_id');
    }

    /**
     * Inverse of `backedBy`: for a physical NIC on a hypervisor, the list of
     * vNICs (across all VMs hosted) that flow through it.
     *
     * @return HasMany<NetworkInterface, $this>
     */
    public function virtualBackedInterfaces(): HasMany
    {
        return $this->hasMany(self::class, 'backed_by_interface_id');
    }

    /**
     * True when this interface participates in the front/rear pairing
     * machinery: keystone ports on patch panels and wall outlets.
     */
    public function requiresSide(): bool
    {
        return $this->type === InterfaceType::Keystone;
    }

    public function isFront(): bool
    {
        return $this->side === InterfaceSide::Front;
    }

    public function isRear(): bool
    {
        return $this->side === InterfaceSide::Rear;
    }

    /**
     * Returns the active connection (if any) on this interface, looking on
     * both endpoints. Returns null when the interface is unconnected.
     */
    public function activeConnection(): ?Connection
    {
        /** @var Connection|null $found */
        $found = Connection::query()
            ->where('status', 'active')
            ->where(function (Builder $q): void {
                $q->where('from_interface_id', $this->getKey())
                    ->orWhere('to_interface_id', $this->getKey());
            })
            ->first();

        return $found;
    }
}
