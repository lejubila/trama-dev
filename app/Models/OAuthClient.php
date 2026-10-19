<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OAuthClientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Public OAuth client registered through Dynamic Client Registration
 * (claude.ai, ChatGPT, Claude Code…). The uuid is the client_id.
 *
 * @property string $id
 * @property string $name
 * @property list<string> $redirect_uris
 * @property string|null $client_uri
 * @property string|null $logo_uri
 */
class OAuthClient extends Model
{
    /** @use HasFactory<OAuthClientFactory> */
    use HasFactory, HasUuids;

    protected $table = 'oauth_clients';

    protected $fillable = ['name', 'redirect_uris', 'client_uri', 'logo_uri'];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array'];
    }

    /**
     * @return HasMany<OAuthGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(OAuthGrant::class, 'client_id');
    }

    public function allowsRedirect(string $uri): bool
    {
        return in_array($uri, $this->redirect_uris, true);
    }
}
