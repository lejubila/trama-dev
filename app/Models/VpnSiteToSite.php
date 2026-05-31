<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VpnProtocol;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\VpnSiteToSiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property VpnProtocol $protocol
 * @property int $endpoint_a_interface_id
 * @property int $endpoint_b_interface_id
 * @property array<int>|null $routed_vlans_a
 * @property array<int>|null $routed_vlans_b
 */
class VpnSiteToSite extends Model implements AuditableContract
{
    /** @use HasFactory<VpnSiteToSiteFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes, TenantAuditable;

    protected $table = 'vpn_site_to_site';

    protected $fillable = [
        'tenant_id',
        'name',
        'protocol',
        'endpoint_a_interface_id',
        'endpoint_b_interface_id',
        'routed_vlans_a',
        'routed_vlans_b',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'protocol' => VpnProtocol::class,
            'routed_vlans_a' => 'array',
            'routed_vlans_b' => 'array',
        ];
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function endpointAInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'endpoint_a_interface_id');
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function endpointBInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'endpoint_b_interface_id');
    }
}
