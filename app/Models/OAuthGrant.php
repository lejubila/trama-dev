<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OAuthGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * An AI client connected by an admin through OAuth: the permissions chosen
 * on the consent page plus the current refresh token.
 *
 * @property int $id
 * @property string $client_id
 * @property int $user_id
 * @property list<string> $abilities
 * @property list<int>|null $tenant_ids
 * @property string|null $resource
 * @property string|null $refresh_token_hash
 * @property string|null $previous_refresh_token_hash
 * @property Carbon|null $refresh_expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class OAuthGrant extends Model implements AuditableContract
{
    /** @use HasFactory<OAuthGrantFactory> */
    use Auditable, HasFactory;

    protected $table = 'oauth_grants';

    protected $fillable = ['client_id', 'user_id', 'abilities', 'tenant_ids', 'resource'];

    /** @var list<string> */
    protected $auditExclude = ['refresh_token_hash', 'previous_refresh_token_hash', 'refresh_expires_at'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'tenant_ids' => 'array',
            'refresh_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    /**
     * @return HasMany<PersonalAccessToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class, 'oauth_grant_id');
    }

    /**
     * @param  Builder<OAuthGrant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('refresh_expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->refresh_expires_at?->isFuture() === true;
    }
}
