<?php

declare(strict_types=1);

use App\Http\Controllers\Export\EquipmentCsvController;
use App\Http\Controllers\Export\RackPdfController;
use App\Http\Controllers\Tenancy\SwitchTenantController;
use App\Livewire\Audit\Trail as AuditTrail;
use App\Livewire\Connections\Edit as ConnectionsEdit;
use App\Livewire\Connections\Index as ConnectionsIndex;
use App\Livewire\Connections\Wizard as ConnectionsWizard;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Equipment\Import as EquipmentImport;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Livewire\Equipment\Show as EquipmentShow;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Racks\Index as RacksIndex;
use App\Livewire\Racks\Show as RacksShow;
use App\Livewire\Settings\ApiTokens as SettingsApiTokens;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Sites\Show as SitesShow;
use App\Livewire\Tags\Manager as TagsManager;
use App\Livewire\Tenants\Index as TenantsIndex;
use App\Livewire\Tenants\Manage as TenantsManage;
use App\Livewire\Topology\Graph as TopologyGraph;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('dashboard', DashboardIndex::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function (): void {
    // Tenancy
    Route::post('tenant/switch/{tenant}', SwitchTenantController::class)->name('tenant.switch');
    Route::get('tenants', TenantsIndex::class)->name('tenants.index');
    Route::get('tenants/{tenant}/manage', TenantsManage::class)->name('tenants.manage');

    // Sites
    Route::get('sites', SitesIndex::class)->name('sites.index');
    Route::get('sites/{site}', SitesShow::class)->name('sites.show');

    // Racks
    Route::get('racks', RacksIndex::class)->name('racks.index');
    Route::get('racks/{rack}', RacksShow::class)->name('racks.show');
    Route::get('racks/{rack}/export.pdf', RackPdfController::class)->name('racks.export.pdf');

    // Equipment
    Route::get('equipment', EquipmentIndex::class)->name('equipment.index');
    Route::get('equipment/import', EquipmentImport::class)->name('equipment.import');
    Route::get('equipment/export.csv', EquipmentCsvController::class)->name('export.equipment.csv');
    Route::get('equipment/template.csv', [EquipmentCsvController::class, 'template'])->name('export.equipment.template');
    Route::get('equipment/{equipment}', EquipmentShow::class)->name('equipment.show');

    // Connections
    Route::get('connections', ConnectionsIndex::class)->name('connections.index');
    Route::get('connections/create', ConnectionsWizard::class)->name('connections.create');
    Route::get('connections/{connection}/edit', ConnectionsEdit::class)->name('connections.edit');

    // Topology
    Route::get('topology', TopologyGraph::class)->name('topology.index');

    // Tags
    Route::get('tags', TagsManager::class)->name('tags.index');

    // Imports history
    Route::get('imports', ImportsIndex::class)->name('imports.index');

    // Audit
    Route::get('audit', AuditTrail::class)->name('audit.index');

    // Notifications
    Route::get('notifications', NotificationsIndex::class)->name('notifications.index');

    // Settings
    Route::get('settings/api-tokens', SettingsApiTokens::class)->name('settings.api-tokens');
});

require __DIR__.'/auth.php';
