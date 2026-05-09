<?php

declare(strict_types=1);

use App\Actions\Import\ImportEquipmentCsv;
use App\Livewire\Layout\NotificationBell;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ImportCompleted;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    TenantContext::clear();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function bootNotifUser(): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->tenants()->attach($tenant, ['role' => 'admin']);
    test()->seed(RolePermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->syncRoles(['admin']);
    actingAsInTenant($user, $tenant);

    return [$tenant, $user];
}

it('importing a CSV dispatches an ImportCompleted notification to the user', function (): void {
    [$tenant, $user] = bootNotifUser();

    $csv = "name,type,vendor,model,serial,firmware,asset_tag,site,room,rack,mounted,position_u_start,position_u_height,status,management_ip,description\n"
        ."NOTIFY-EQ,switch,Cisco,X,,,,,,,false,,,active,,\n";
    Storage::disk('local')->makeDirectory('imports');
    $rel = 'imports/'.uniqid('notif-').'.csv';
    Storage::disk('local')->put($rel, $csv);

    app(ImportEquipmentCsv::class)->execute(
        absolutePath: Storage::disk('local')->path($rel),
        userId: $user->getKey(),
    );

    expect($user->fresh()->notifications()->count())->toBe(1);
    expect($user->fresh()->notifications()->first()?->type)->toBe(ImportCompleted::class);
});

it('the bell shows the unread count', function (): void {
    [$tenant, $user] = bootNotifUser();
    DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'X',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->getKey(),
        'data' => ['title' => 'Hello'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::test(NotificationBell::class)
        ->call('toggle') // open the dropdown so the latest items render
        ->assertSee('Hello');
});

it('mark all as read clears the unread badge', function (): void {
    [$tenant, $user] = bootNotifUser();
    $n = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'X',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->getKey(),
        'data' => ['title' => 'unread'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->unreadNotifications()->count())->toBe(1);

    Livewire::test(NotificationBell::class)->call('markAllRead');

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
