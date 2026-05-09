<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
