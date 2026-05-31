<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\WifiAssociationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $wifi_network_id
 * @property int $client_interface_id
 * @property int|null $preferred_broadcaster_interface_id
 */
class WifiAssociation extends Model implements AuditableContract
{
    /** @use HasFactory<WifiAssociationFactory> */
    use BelongsToTenant, HasFactory, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'wifi_network_id',
        'client_interface_id',
        'preferred_broadcaster_interface_id',
    ];

    /**
     * @return BelongsTo<WifiNetwork, $this>
     */
    public function wifiNetwork(): BelongsTo
    {
        return $this->belongsTo(WifiNetwork::class);
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function clientInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'client_interface_id');
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function preferredBroadcaster(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'preferred_broadcaster_interface_id');
    }
}
