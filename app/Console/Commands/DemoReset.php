<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Icons\DefaultIcons;
use App\Services\TenantTransfer\TenantArchiveImporter;
use App\Services\TenantTransfer\TenantArchiveReader;
use App\Support\Demo;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Wipes every piece of data and restores the demo dataset. Destructive by
 * design, so it refuses to run unless DEMO_MODE is on (or --force is given,
 * which the test-suite uses).
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset
        {--force : Run even when DEMO_MODE is off (destroys all data!)}
        {--no-maintenance : Do not put the app in maintenance mode while resetting}';

    protected $description = 'Wipe all data and restore the demo dataset (demo mode only)';

    /** Tables that survive the wipe: the schema bookkeeping only. */
    private const KEEP_TABLES = ['migrations'];

    /** Directories on the public disk holding user-uploaded/generated files. */
    private const FILE_DIRS = ['icons', 'floor-plans', 'rack-photos', 'documents', 'topology-snapshots', 'imported'];

    public function handle(TenantArchiveReader $reader, TenantArchiveImporter $importer): int
    {
        if (! Demo::enabled() && ! $this->option('force')) {
            $this->error('DEMO_MODE is off: refusing to wipe the database. Use --force only if you really mean it.');

            return self::FAILURE;
        }

        $maintenance = ! $this->option('no-maintenance');
        if ($maintenance) {
            Artisan::call('down', ['--retry' => 60]);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->wipeDatabase();
            $this->wipeFiles();
            $demoUser = $this->createDemoUser();
            // Global icons are not part of tenant archives: restore the bundled set.
            (new DefaultIcons)->install();
            $imported = $this->restoreData($reader, $importer, $demoUser);
            Demo::markReset();

            $this->info("Demo restored: {$imported} customer(s) loaded, demo user {$demoUser->email}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Demo reset failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            TenantContext::clear();
            if ($maintenance) {
                Artisan::call('up');
            }
        }
    }

    private function wipeDatabase(): void
    {
        $tables = array_values(array_diff(
            array_map(fn ($t) => is_array($t) ? $t['name'] : $t, Schema::getTables()),
            self::KEEP_TABLES,
        ));
        if ($tables === []) {
            return;
        }

        // RESTART IDENTITY keeps ids stable across resets (the demo user is
        // always #1), so sessions opened before the reset stay valid.
        $quoted = implode(', ', array_map(fn ($t) => '"'.$t.'"', $tables));
        DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");
    }

    private function wipeFiles(): void
    {
        $disk = Storage::disk('public');
        foreach (self::FILE_DIRS as $dir) {
            $disk->deleteDirectory($dir);
        }
    }

    private function createDemoUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => config('demo.user.name'),
            'email' => config('demo.user.email'),
            'password' => Hash::make((string) config('demo.user.password')),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Loads every tenant archive found at DEMO_ARCHIVE (a .zip or a
     * directory of them). Without archives the bundled seeders are used,
     * so a fresh demo is never empty.
     */
    private function restoreData(TenantArchiveReader $reader, TenantArchiveImporter $importer, User $demoUser): int
    {
        $archives = $this->archivePaths();

        if ($archives === []) {
            $this->warn('No demo archive found: falling back to the built-in demo seeders.');
            $this->callSilently('db:seed', ['--class' => TenantSeeder::class, '--force' => true]);
            $this->callSilently('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

            return 2;
        }

        foreach ($archives as $path) {
            $archive = $reader->read($path);
            try {
                $tenant = $archive->tenant();
                $importer->import($archive, $demoUser, [
                    'name' => $tenant['name'],
                    'slug' => null,
                    'domain' => $tenant['domain'],
                ]);
            } finally {
                $archive->close();
            }
        }

        // The importer leaves the last imported tenant active; start on the first.
        $demoUser->forceFill(['current_tenant_id' => DB::table('tenants')->min('id')])->save();

        return count($archives);
    }

    /**
     * @return list<string>
     */
    private function archivePaths(): array
    {
        $configured = (string) config('demo.archive');
        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);

        if (is_file($path)) {
            return [$path];
        }
        if (is_dir($path)) {
            $files = glob(rtrim($path, '/').'/*.zip') ?: [];
            sort($files);

            return $files;
        }

        return [];
    }
}
