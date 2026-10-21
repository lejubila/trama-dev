<?php

declare(strict_types=1);

use App\Support\Demo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/**
 * Cleanup task: drop generated export artifacts older than 24 hours.
 * Imports are kept (they're the audit trail of what landed in the DB).
 */
Artisan::command('exports:cleanup {--hours=24 : retention window}', function (): void {
    $hours = (int) $this->option('hours');
    $cutoff = now()->subHours($hours)->getTimestamp();
    $disk = Storage::disk('local');

    $deleted = 0;
    foreach ($disk->files('exports') as $path) {
        if ($disk->lastModified($path) < $cutoff) {
            $disk->delete($path);
            $deleted++;
        }
    }

    $this->info("Removed {$deleted} expired export artifact(s).");
})->purpose('Delete export files older than --hours');

Schedule::command('exports:cleanup')->dailyAt('03:00');

// Public demo: wipe and restore the demo dataset once a day. The command
// itself also refuses to run when DEMO_MODE is off.
Schedule::command('demo:reset')
    ->dailyAt((string) config('demo.reset_time', '03:00'))
    ->timezone((string) config('demo.timezone', 'Europe/Rome'))
    ->when(fn (): bool => Demo::enabled())
    ->withoutOverlapping();

// OAuth for the MCP endpoint: expired codes, ended connections, unused clients.
Schedule::command('oauth:prune')->dailyAt('03:30');
