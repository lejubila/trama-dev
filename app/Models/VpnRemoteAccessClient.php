<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\VpnRemoteAccessClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $vpn_remote_access_id
 * @property int $client_interface_id
 * @property string|null $username
 */
class VpnRemoteAccessClient extends Model implements AuditableContract
{
    /** @use HasFactory<VpnRemoteAccessClientFactory> */
    use BelongsToTenant, HasFactory, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'vpn_remote_access_id',
        'client_interface_id',
        'username',
    ];

    /**
     * @return BelongsTo<VpnRemoteAccess, $this>
     */
    public function vpnRemoteAccess(): BelongsTo
    {
        return $this->belongsTo(VpnRemoteAccess::class);
    }

    /**
     * @return BelongsTo<NetworkInterface, $this>
     */
    public function clientInterface(): BelongsTo
    {
        return $this->belongsTo(NetworkInterface::class, 'client_interface_id');
    }
}
