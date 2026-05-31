<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\TenantAuditable;
use Database\Factories\WifiNetworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $ssid
 * @property string|null $security_type
 * @property int|null $vlan_id
 * @property bool $hidden_ssid
 * @property string|null $notes
 */
class WifiNetwork extends Model implements AuditableContract
{
    /** @use HasFactory<WifiNetworkFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes, TenantAuditable;

    protected $fillable = [
        'tenant_id',
        'ssid',
        'security_type',
        'vlan_id',
        'hidden_ssid',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hidden_ssid' => 'boolean',
            'vlan_id' => 'integer',
        ];
    }

    /**
     * AP / controller wireless interfaces that broadcast this SSID.
     *
     * @return BelongsToMany<NetworkInterface, $this>
     */
    public function broadcasters(): BelongsToMany
    {
        return $this->belongsToMany(NetworkInterface::class, 'wifi_network_broadcasters', 'wifi_network_id', 'interface_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WifiAssociation, $this>
     */
    public function associations(): HasMany
    {
        return $this->hasMany(WifiAssociation::class);
    }
}
