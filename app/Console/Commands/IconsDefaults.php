<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Icons\DefaultIcons;
use Illuminate\Console\Command;

class IconsDefaults extends Command
{
    protected $signature = 'icons:defaults
        {--export : Overwrite the bundled set with the global icons configured in this installation}
        {--path= : Directory of the bundled set (default: database/icons)}';

    protected $description = 'Install the bundled default global icons (missing kinds only), or export the current ones';

    public function handle(): int
    {
        $path = $this->option('path');
        $icons = new DefaultIcons(is_string($path) && $path !== '' ? $path : null);

        if ($this->option('export')) {
            $count = $icons->export();
            $this->info("Exported {$count} global icon(s) to {$icons->directory()}.");

            return self::SUCCESS;
        }

        $count = $icons->install();
        $this->info($count > 0 ? "Installed {$count} default global icon(s)." : 'Every kind already has a global icon: nothing to do.');

        return self::SUCCESS;
    }
}
