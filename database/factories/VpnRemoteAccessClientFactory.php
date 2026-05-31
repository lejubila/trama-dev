<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VpnRemoteAccessClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VpnRemoteAccessClient>
 */
class VpnRemoteAccessClientFactory extends Factory
{
    protected $model = VpnRemoteAccessClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => null,
        ];
    }
}
