<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OAuthClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthClient>
 */
class OAuthClientFactory extends Factory
{
    protected $model = OAuthClient::class;

    public function definition(): array
    {
        return [
            'name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ];
    }
}
