<?php

declare(strict_types=1);

use App\Livewire\Rooms\PlanEditor;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Livewire\Livewire;

afterEach(function (): void {
    TenantContext::clear();
});

function setupPlanEditorContext(string $role = 'tecnico'): array
{
    $tenant = Tenant::factory()->create();
    /** @var User $user */
    $user = User::factory()->create();
    $user->forceFill(['role' => $role])->save();
    $user->tenants()->attach($tenant);
    $user->forceFill(['current_tenant_id' => $tenant->getKey()])->save();
    actingAsInTenant($user, $tenant);

    $site = Site::factory()->create();
    $room = Room::factory()->create([
        'site_id' => $site->getKey(),
        'width_m' => 10,
        'depth_m' => 6,
    ]);

    return [$tenant, $user, $room];
}

it('mounts with an empty drawing when none is persisted', function (): void {
    [, , $room] = setupPlanEditorContext();

    Livewire::test(PlanEditor::class, ['room' => $room])
        ->assertOk()
        ->assertSet('drawing.walls', [])
        ->assertSet('drawing.doors', [])
        ->assertSet('drawing.windows', [])
        ->assertSet('drawing.labels', []);
});

it('persists a normalized drawing payload via savePlan', function (): void {
    [, , $room] = setupPlanEditorContext();

    $payload = [
        'walls' => [
            ['id' => 'w1', 'points' => [[0, 0], [5, 0], [5, 4]], 'thickness_m' => 0.2],
            // invalid (only 1 point) — must be dropped
            ['id' => 'bad', 'points' => [[0, 0]]],
        ],
        'doors' => [
            ['id' => 'd1', 'wall_id' => 'w1', 't' => 0.3, 'width_m' => 0.9, 'swing' => 'right_in'],
            // anchored to a non-existent wall — must be dropped
            ['id' => 'd2', 'wall_id' => 'ghost', 't' => 0.5, 'width_m' => 0.9, 'swing' => 'left_in'],
        ],
        'windows' => [
            ['id' => 'win1', 'wall_id' => 'w1', 't' => 0.7, 'width_m' => 1.0],
        ],
        'labels' => [
            ['id' => 'l1', 'pos' => [2, 3], 'text' => 'Sala server'],
            // empty text — must be dropped
            ['id' => 'l2', 'pos' => [1, 1], 'text' => '   '],
        ],
    ];

    Livewire::test(PlanEditor::class, ['room' => $room])
        ->call('savePlan', $payload)
        ->assertOk();

    $room->refresh();
    $stored = $room->floor_plan_drawing;
    expect($stored)->toBeArray()
        ->and($stored['version'])->toBe(1)
        ->and($stored['walls'])->toHaveCount(1)
        ->and($stored['walls'][0]['id'])->toBe('w1')
        ->and($stored['doors'])->toHaveCount(1)
        ->and($stored['doors'][0]['swing'])->toBe('right_in')
        ->and($stored['windows'])->toHaveCount(1)
        ->and($stored['labels'])->toHaveCount(1)
        ->and($stored['labels'][0]['text'])->toBe('Sala server');
});

it('clamps invalid dimensions and rejects unknown swing values', function (): void {
    [, , $room] = setupPlanEditorContext();

    Livewire::test(PlanEditor::class, ['room' => $room])
        ->call('savePlan', [
            'walls' => [['id' => 'w1', 'points' => [[0, 0], [5, 0]], 'thickness_m' => 99]],
            'doors' => [['id' => 'd1', 'wall_id' => 'w1', 't' => 5, 'width_m' => 99, 'swing' => 'spin']],
        ])
        ->assertOk();

    $room->refresh();
    $stored = $room->floor_plan_drawing;
    expect((float) $stored['walls'][0]['thickness_m'])->toBe(1.0)
        ->and((float) $stored['doors'][0]['t'])->toBe(1.0)
        ->and((float) $stored['doors'][0]['width_m'])->toBe(3.0)
        ->and($stored['doors'][0]['swing'])->toBe('left_in');
});

it('clearPlan wipes the persisted drawing', function (): void {
    [, , $room] = setupPlanEditorContext();
    $room->update(['floor_plan_drawing' => [
        'version' => 1,
        'walls' => [['id' => 'w1', 'points' => [[0, 0], [1, 0]], 'thickness_m' => 0.15]],
        'doors' => [], 'windows' => [], 'labels' => [],
    ]]);

    Livewire::test(PlanEditor::class, ['room' => $room])
        ->call('clearPlan')
        ->assertOk();

    $room->refresh();
    expect($room->floor_plan_drawing)->toBeNull();
});

it('denies the editor to a cliente role', function (): void {
    [, , $room] = setupPlanEditorContext('cliente');

    Livewire::test(PlanEditor::class, ['room' => $room])
        ->assertForbidden();
});
