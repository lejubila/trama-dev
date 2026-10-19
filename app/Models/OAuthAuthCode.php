<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Single-use authorization code (stored hashed) carrying the permissions
 * the admin approved, until the client exchanges it for tokens.
 *
 * @property int $id
 * @property string $code_hash
 * @property string $client_id
 * @property int $user_id
 * @property list<string> $abilities
 * @property list<int>|null $tenant_ids
 * @property string $redirect_uri
 * @property string $code_challenge
 * @property string|null $resource
 * @property int|null $grant_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
class OAuthAuthCode extends Model
{
    protected $table = 'oauth_auth_codes';

    protected $fillable = [
        'code_hash', 'client_id', 'user_id', 'abilities', 'tenant_ids',
        'redirect_uri', 'code_challenge', 'resource', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'tenant_ids' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
