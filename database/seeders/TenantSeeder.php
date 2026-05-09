<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->updateOrCreate(
            ['slug' => 'acme'],
            ['name' => 'ACME Spa', 'settings' => []],
        );

        Tenant::query()->updateOrCreate(
            ['slug' => 'beta'],
            ['name' => 'Beta Srl', 'settings' => []],
        );
    }
}
