# FASE 2 — Modello dati core

> Obiettivo: tutte le tabelle di dominio create, models con relazioni corrette,
> factory + seeder che producono un dataset realistico, policy per ogni model.
>
> **Riferimento dettagliato:** `docs/DATA_MODEL.md` (campo per campo).

## Ordine delle migrations (rispetta le foreign key!)

1. `create_sites_table`
2. `create_rooms_table`
3. `create_racks_table`
4. `create_equipment_table`
5. `create_interfaces_table`
6. `create_connections_table`
7. `create_link_groups_table`
8. `create_link_group_connection_table`
9. `create_tags_table`
10. `create_taggables_table`

## Convenzioni applicate ovunque

- Ogni tabella di dominio ha `tenant_id` (foreignId, constrained, cascadeOnDelete)
- Ogni tabella ha `timestamps()`
- Soft delete dove indicato in DATA_MODEL.md (`softDeletes()`)
- Indici espliciti per le FK più consultate
- Per i campi enum, vincolo CHECK applicativo via PHP enum (NO check SQL — preferiamo l'enum PHP)

## Vincoli speciali da implementare in migration

### Connections — unique parziale
```php
DB::statement("
  CREATE UNIQUE INDEX connections_from_active_unique
    ON connections (from_interface_id) WHERE status = 'active'
");
DB::statement("
  CREATE UNIQUE INDEX connections_to_active_unique
    ON connections (to_interface_id) WHERE status = 'active'
");
```
E nel `down()` corrispondente `DROP INDEX`.

### Equipment — overlap U
NON in SQL (troppo complesso). Validazione in `App\Services\RackPlacementService::canPlace(...)`.

## Models

Ogni model include:
- `protected $fillable = [...]` esplicito
- `protected $casts = [...]` per JSON, enum, datetime
- Trait: `BelongsToTenant`, `HasFactory`, `SoftDeletes` (dove presente)
- Trait `Auditable` (owen-it/laravel-auditing) — solo su: Equipment, NetworkInterface, Connection
- Relazioni Eloquent complete (vedi DATA_MODEL.md)
- Enum dedicati in `app/Enums/` (`EquipmentType`, `InterfaceType`, `ConnectionStatus`, ...)

### Esempio Equipment
```php
namespace App\Models;

use App\Enums\EquipmentType;
use App\Enums\EquipmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Equipment extends Model implements AuditableContract
{
    use BelongsToTenant, HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'tenant_id', 'rack_id', 'name', 'type', 'vendor', 'model',
        'serial', 'firmware', 'asset_tag', 'mounted',
        'position_u_start', 'position_u_height', 'position_orient',
        'status', 'management_ip', 'description', 'custom_fields',
    ];

    protected $casts = [
        'type'             => EquipmentType::class,
        'status'           => EquipmentStatus::class,
        'mounted'          => 'boolean',
        'custom_fields'    => 'array',
    ];

    public function rack()       { return $this->belongsTo(Rack::class); }
    public function interfaces() { return $this->hasMany(NetworkInterface::class); }
    public function tags()       { return $this->morphToMany(Tag::class, 'taggable'); }

    public function isRackMounted(): bool
    {
        return $this->mounted && $this->rack_id !== null;
    }

    public function rackUnitsRange(): ?array
    {
        if (! $this->isRackMounted()) return null;
        $start = $this->position_u_start;
        return range($start, $start + $this->position_u_height - 1);
    }
}
```

## Enum di esempio
```php
namespace App\Enums;

enum EquipmentType: string
{
    case Switch        = 'switch';
    case Router        = 'router';
    case Firewall      = 'firewall';
    case AccessPoint   = 'access_point';
    case Controller    = 'controller';
    case PatchPanel    = 'patch_panel';
    case Server        = 'server';
    case Ups           = 'ups';
    case Pdu           = 'pdu';
    case MediaConverter= 'media_converter';
    case Nas           = 'nas';
    case Kvm           = 'kvm';
    case Other         = 'other';

    public function label(): string
    {
        return match($this) {
            self::Switch        => 'Switch',
            self::Router        => 'Router',
            self::Firewall      => 'Firewall',
            self::AccessPoint   => 'Access Point',
            self::Controller    => 'Controller',
            self::PatchPanel    => 'Patch Panel',
            self::Server        => 'Server',
            self::Ups           => 'UPS',
            self::Pdu           => 'PDU',
            self::MediaConverter=> 'Media Converter',
            self::Nas           => 'NAS',
            self::Kvm           => 'KVM',
            self::Other         => 'Altro',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Switch       => 'cyan',
            self::Router       => 'violet',
            self::Firewall     => 'red',
            self::AccessPoint  => 'emerald',
            self::Controller   => 'amber',
            self::PatchPanel   => 'slate',
            self::Server       => 'blue',
            self::Ups, self::Pdu => 'yellow',
            default            => 'gray',
        };
    }
}
```

## Policies

Una policy per ogni model: `SitePolicy`, `RoomPolicy`, `RackPolicy`, `EquipmentPolicy`,
`NetworkInterfacePolicy`, `ConnectionPolicy`, `TagPolicy`.

Pattern comune:
```php
public function viewAny(User $u): bool { return $u->hasAnyRoleInCurrentTenant(['admin','tecnico','cliente']); }
public function view(User $u, Model $m): bool { return $u->belongsToTenant($m->tenant); }
public function create(User $u): bool { return $u->hasAnyRoleInCurrentTenant(['admin','tecnico']); }
public function update(User $u, Model $m): bool { return $u->hasAnyRoleInCurrentTenant(['admin','tecnico']) && $u->belongsToTenant($m->tenant); }
public function delete(User $u, Model $m): bool { return $u->hasRoleInCurrentTenant('admin') && $u->belongsToTenant($m->tenant); }
```
Registra tutte le policy in `AuthServiceProvider`.

## Factory

Ogni model ha una factory che genera dati realistici:
- `EquipmentFactory`: nome `SW-{n}`, vendor random tra Cisco/HP/Juniper/MikroTik/Fortinet/Ubiquiti, model coerente
- `NetworkInterfaceFactory::ethernet()`, `::fiber()`, `::wireless()` (state methods)
- `ConnectionFactory`: richiede esplicitamente from/to interface (no auto-discovery)

## Seeder esteso `DemoDataSeeder`

Estende lo scenario di fase 1 aggiungendo per il tenant ACME:
- 2 Sedi (Milano, Roma)
- 3 Rooms totali (CED Milano, CED Roma, Edge Roma)
- 4 Racks
- ~20 Equipment vari (1 firewall, 2 router, 4 switch core, 6 switch access, 1 controller, 4 AP, 2 patch panel)
- ~120 Interfacce
- ~30 Connessioni che disegnano una topologia gerarchica realistica

Per il tenant Beta uno scenario minimale (1 sede, 1 rack, 5 dispositivi).

## Test (Pest)

Per ogni model:
- `it('belongs to a tenant and is auto-scoped')`
- `it('cannot be created without a tenant context')`
- `it('cascades correctly on parent delete')` (dove applicabile)

Test specifici:
- `EquipmentTest`: `it('cannot overlap U positions in the same rack')` — RackPlacementService
- `ConnectionTest`: `it('refuses second active connection on the same interface')` — vincolo unique
- `ConnectionTest`: `it('refuses connection between interfaces of different tenants')` — gate check
- `ConnectionTest`: `it('refuses self-connection')`
- `PolicyTest` per ogni policy con i 3 ruoli.

## Service classes

Crea (saranno usati nelle fasi successive):

### `App\Services\RackPlacementService`
- `canPlace(Rack $rack, int $startU, int $heightU, ?Equipment $excluding = null): bool`
- `getOccupiedUnits(Rack $rack): array`
- `findAvailableSlots(Rack $rack, int $heightU): array`

### `App\Services\ConnectionService`
- `connect(NetworkInterface $a, NetworkInterface $b, array $cableData): Connection`
  - Valida: stesso tenant, non self, entrambe libere
  - Crea record in transazione

### `App\Services\TopologyService`
- `buildGraph(int $siteId = null): array` — restituisce `['nodes' => [...], 'edges' => [...]]`
  in formato Cytoscape.js elements

## Definition of Done

- [ ] Tutte le migrations applicate, `migrate:fresh --seed` funziona
- [ ] `php artisan db:seed --class=DemoDataSeeder` produce lo scenario descritto
- [ ] Tutti i model hanno trait, casts, relazioni, factory
- [ ] Tutte le policy registrate
- [ ] Test fase 2 verdi
- [ ] In tinker: switching tenant context cambia ciò che si vede (`Equipment::count()`)
- [ ] Commit: `feat: phase 2 — core data model`

➡️ Procedi alla **FASE 3**.
