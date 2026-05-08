# FASE 3 — CRUD Livewire

> Obiettivo: l'utente può fare CRUD completo su Sedi, Locali, Rack, Dispositivi, Interfacce,
> Connessioni tramite componenti Livewire, con validazione, autorizzazione, audit attivo.

## Layout principale

Crea il layout `resources/views/components/layouts/app.blade.php` con:
- Topbar (logo, tenant selector, search, user menu, theme toggle)
- Sidebar con voci di menu
- Slot principale `{{ $slot }}`
- Toast container per notifiche Livewire (`<x-toast />`)
- Drawer container globale

## Componenti Livewire da creare

### Sites
- `App\Livewire\Sites\Index` — tabella sedi
- `App\Livewire\Sites\Form` — modal/page per create/edit
- `App\Livewire\Sites\Show` — dettaglio con tab Locali

### Rooms
- `App\Livewire\Rooms\Form` (modal, da Site\Show)

### Racks
- `App\Livewire\Racks\Index` — tabella rack di una room
- `App\Livewire\Racks\Form` — create/edit
- `App\Livewire\Racks\Show` — dettaglio (in fase 4 ci aggiungeremo l'elevation SVG)

### Equipment
- `App\Livewire\Equipment\Index` — tabella cross-rack con filtri
- `App\Livewire\Equipment\Form` — create/edit (modal)
- `App\Livewire\Equipment\Drawer` — drawer di dettaglio con tab Generale/Interfacce/Connessioni/Audit

### Interfaces
- `App\Livewire\Interfaces\Table` — embedded nel drawer Equipment, tab "Interfacce"
- `App\Livewire\Interfaces\Form` — create/edit modal

### Connections
- `App\Livewire\Connections\Index` — tabella connessioni
- `App\Livewire\Connections\Wizard` — wizard 3-step di creazione

### Tags
- `App\Livewire\Tags\Manager` — gestione tag del tenant

## Pattern componente Livewire

```php
namespace App\Livewire\Equipment;

use App\Models\Equipment;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;

class Form extends Component
{
    public ?Equipment $equipment = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|in:switch,router,firewall,access_point,controller,patch_panel,server,ups,pdu,media_converter,nas,kvm,other')]
    public string $type = 'switch';

    // ... altri campi

    public function mount(?int $equipmentId = null): void
    {
        if ($equipmentId) {
            $this->equipment = Equipment::findOrFail($equipmentId);
            $this->authorize('update', $this->equipment);
            $this->fillFromModel();
        } else {
            $this->authorize('create', Equipment::class);
        }
    }

    public function save(): void
    {
        $data = $this->validate();
        $this->equipment
            ? $this->equipment->update($data)
            : $this->equipment = Equipment::create($data);

        $this->dispatch('equipment-saved', id: $this->equipment->id);
        $this->dispatch('toast', type: 'success', message: 'Dispositivo salvato.');
    }

    public function render()
    {
        return view('livewire.equipment.form');
    }
}
```

## Form Requests / Validazione

Per evitare di duplicare regole di validazione tra Livewire e (futuri) controller API,
estrai le regole in classi dedicate:

```php
namespace App\Validation;

class EquipmentRules
{
    public static function rules(?int $id = null): array
    {
        return [
            'name'  => 'required|string|max:150',
            'type'  => ['required', new Enum(EquipmentType::class)],
            'rack_id' => 'nullable|exists:racks,id',
            'mounted' => 'boolean',
            'position_u_start' => 'nullable|integer|min:1',
            'position_u_height' => 'nullable|integer|min:1',
            // ...
        ];
    }
}
```

## Validazioni di dominio

Oltre alle rules, valida via Service:
- Quando `mounted=true` → richiama `RackPlacementService::canPlace(...)` e aggiungi
  `addError('position_u_start', 'Conflitto con altro dispositivo o supera l\'altezza del rack')`
- Quando si crea connessione → richiama `ConnectionService` e cattura eccezioni domain

## Autorizzazione

Ogni componente:
- `mount()`: `$this->authorize($action, $model_or_class)`
- `save()`/`delete()`: idem prima di mutare
- Le viste mostrano/nascondono bottoni con `@can('update', $row)`

## Audit log UI

Componente `App\Livewire\Audit\Trail` che:
- Mostra ultimi 50 audit del tenant corrente
- Filtri per modello, utente, periodo
- Espandi riga → diff JSON (vecchio/nuovo)

Disponibile come pagina "Audit" e come tab dentro il drawer Equipment.

## Search globale

Componente `App\Livewire\GlobalSearch` nella topbar:
- Input con debounce 300ms
- Cerca su Equipment.name, Equipment.serial, NetworkInterface.name+IP, Site.name
- Risultati raggruppati per tipo, click → naviga al dettaglio

## Notifiche

Pattern: i componenti dispatchano evento `toast` con `type` e `message`. Un componente
globale `App\Livewire\Toaster` ascolta e mostra notifiche temporanee.

## Tabelle

Crea un componente Blade riutilizzabile `<x-data-table />` che gestisce:
- Header con sort
- Search box
- Paginazione
- Bulk select
- Slot per riga, slot per actions

I componenti Livewire estendono `Livewire\WithPagination`.

## Test

Per ogni componente Livewire principale:
```php
it('renders index page')
it('creates new record')
it('updates existing record')
it('validates required fields')
it('forbids access for cliente role')
it('isolates data between tenants')
```

Esempio:
```php
use function Pest\Livewire\livewire;

it('creates a new equipment', function () {
    $tenant = Tenant::factory()->create();
    $rack = Rack::factory()->for($tenant)->create();
    $admin = User::factory()->withTenant($tenant, 'admin')->create();

    actingAsInTenant($admin, $tenant);

    livewire(\App\Livewire\Equipment\Form::class)
        ->set('name', 'SW-TEST-01')
        ->set('type', 'switch')
        ->set('rack_id', $rack->id)
        ->set('mounted', true)
        ->set('position_u_start', 1)
        ->set('position_u_height', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect(Equipment::where('name', 'SW-TEST-01')->exists())->toBeTrue();
});
```

## Definition of Done

- [ ] Tutte le route Livewire registrate (`routes/web.php`)
- [ ] CRUD completo funzionante via UI per ogni entità
- [ ] Validazione client-side (Alpine) + server-side (Livewire)
- [ ] Audit log visibile e popolato dalle modifiche
- [ ] Test fase 3 verdi (>= 30 test)
- [ ] Linter/PHPStan puliti
- [ ] Commit: `feat: phase 3 — Livewire CRUD`

➡️ Procedi alla **FASE 4**.
