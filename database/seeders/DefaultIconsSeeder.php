<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Icons\DefaultIcons;
use Illuminate\Database\Seeder;

class DefaultIconsSeeder extends Seeder
{
    public function run(): void
    {
        (new DefaultIcons)->install();
    }
}
