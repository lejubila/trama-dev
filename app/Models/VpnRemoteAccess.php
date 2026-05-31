<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VpnProtocol;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\VpnRemoteAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property VpnProtocol $protocol
 * @property int $firewall_interface_id
 * @property array<int>|null $routed_vlans
 * @property string|null $notes
 */
class VpnRemoteAccess extends Model implements AuditableContract
{
    /** @use HasFactory<VpnRemoteAccessFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes, TenantAuditable;

    protected $table = 'vpn_remote_access';

    protected $fillable = [
        'tenant_id',
        'name',
        'protocol',
        'firewall_interface_id',
        'routed_vlans',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'protocol' => VpnProtocol::class,
            'routed_vlans' => 'array',
        ];
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function firewallInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'firewall_interface_id');
    }

    /**
     * @return HasMany<VpnRemoteAccessClient, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(VpnRemoteAccessClient::class);
    }
}
