<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\OAuthClient;
use App\Models\OAuthGrant;
use App\Models\User;
use App\Support\Api\TokenAbilities;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthGrant>
 */
class OAuthGrantFactory extends Factory
{
    protected $model = OAuthGrant::class;

    public function definition(): array
    {
        return [
            'client_id' => OAuthClient::factory(),
            'user_id' => User::factory()->state(['role' => UserRole::Admin]),
            'abilities' => TokenAbilities::readOnly(),
            'tenant_ids' => null,
        ];
    }
}
