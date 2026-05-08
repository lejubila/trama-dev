# FASE 6 — Export & Import

> Obiettivo: l'utente può esportare diagrammi in PDF/PNG e fare import/export massivo
> di dispositivi via CSV.

## Export PDF dei diagrammi

### Opzione A — Vista Rack (più semplice)
Pagina dedicata "stampabile" `/racks/{rack}/print` che renderizza solo il rack elevation
in un layout pulito (no sidebar, no toolbar, formato A4 portrait).

Servizio `App\Services\Export\PdfExporter`:
```php
use Spatie\Browsershot\Browsershot;

public function rackPdf(Rack $rack): string
{
    $url = route('racks.print', $rack);
    $token = $this->createSignedTokenFor(auth()->user(), $rack);
    $path = storage_path("app/exports/rack-{$rack->id}-" . Str::uuid() . ".pdf");

    Browsershot::url($url . '?token=' . $token)
        ->format('A4')
        ->showBackground()
        ->waitUntilNetworkIdle()
        ->setNodeBinary(env('BROWSERSHOT_NODE_BINARY'))
        ->setChromePath(env('BROWSERSHOT_CHROME_PATH'))
        ->save($path);

    return $path;
}
```

> Nota: la pagina `/racks/{rack}/print` deve essere accessibile dal container app
> a `http://nginx/racks/...`. Se si avvia Browsershot dentro lo stesso container,
> dobbiamo usare il network Docker (host = `nginx`, non `localhost`).

Configura una rotta speciale che accetta un **signed token** (firmato con app key)
così Browsershot può accedere alla pagina senza autenticazione di sessione.

### Opzione B — Vista Topologica
Più complesso perché Cytoscape è JS lato browser. Soluzione:
- L'utente clicca "Esporta PDF" → frontend cattura `cy.png({ full: true, scale: 2 })`
- Invia il PNG (base64) a `POST /export/topology-pdf` come Livewire upload
- Backend embedda il PNG in un template Blade A4 con header/footer e lo converte in PDF via Browsershot

## Export PNG
- Vista rack: server-side renderizza l'SVG, lo converte in PNG via Browsershot (`->screenshot()`)
- Vista topologia: lato client come sopra

## Action `App\Actions\Export\GenerateExport`

```php
class GenerateExport
{
    public function __construct(private PdfExporter $pdfExporter) {}

    public function execute(string $type, Model $subject, User $user): string
    {
        return match ($type) {
            'rack-pdf'     => $this->pdfExporter->rackPdf($subject),
            'rack-png'     => $this->pdfExporter->rackPng($subject),
            'topology-pdf' => $this->pdfExporter->topologyPdf($subject, $user),
            default        => throw new InvalidArgumentException("Unknown export: {$type}"),
        };
    }
}
```

L'esportazione gira in **queue** (`ExportRackPdfJob`) e notifica l'utente quando pronta:
toast + link di download. Per export brevi (<3s) si può fare sincrono.

Storage: `storage/app/exports/`. Cleanup automatico via task scheduler (file > 24h eliminati).

## Export CSV — Equipment

Action `App\Actions\Export\ExportEquipmentCsv`:

```php
use League\Csv\Writer;

public function execute(?int $siteId = null): string
{
    $path = storage_path('app/exports/equipment-' . now()->format('Ymd-His') . '.csv');
    $csv = Writer::createFromPath($path, 'w+');
    $csv->insertOne([
        'name', 'type', 'vendor', 'model', 'serial', 'firmware', 'asset_tag',
        'site', 'room', 'rack', 'mounted', 'position_u_start', 'position_u_height',
        'status', 'management_ip', 'description'
    ]);

    Equipment::query()
        ->when($siteId, fn ($q) => $q->whereHas('rack.room', fn ($q) => $q->where('site_id', $siteId)))
        ->with('rack.room.site')
        ->lazy()
        ->each(function (Equipment $eq) use ($csv) {
            $csv->insertOne([
                $eq->name,
                $eq->type->value,
                $eq->vendor,
                $eq->model,
                $eq->serial,
                $eq->firmware,
                $eq->asset_tag,
                $eq->rack?->room?->site?->name,
                $eq->rack?->room?->name,
                $eq->rack?->name,
                $eq->mounted ? 'true' : 'false',
                $eq->position_u_start,
                $eq->position_u_height,
                $eq->status->value,
                $eq->management_ip,
                $eq->description,
            ]);
        });

    return $path;
}
```

## Import CSV — Equipment

Componente Livewire `App\Livewire\Equipment\Import`:
- Step 1: upload file
- Step 2: preview prime 10 righe + mapping colonne (auto-detect se header standard)
- Step 3: validazione completa, mostra errori riga per riga
- Step 4: conferma import (dentro transaction)

Action `App\Actions\Import\ImportEquipmentCsv`:
```php
public function execute(string $path, array $mapping, int $tenantId): ImportResult
{
    $result = new ImportResult;
    DB::transaction(function () use ($path, $mapping, $tenantId, $result) {
        $csv = Reader::createFromPath($path);
        $csv->setHeaderOffset(0);
        foreach ($csv as $rowIdx => $row) {
            try {
                $data = $this->mapRow($row, $mapping);
                $validator = Validator::make($data, EquipmentRules::rulesForImport());
                if ($validator->fails()) {
                    $result->addError($rowIdx + 2, $validator->errors()->all());
                    continue;
                }
                $rack = $this->resolveRackByPath($data, $tenantId); // crea/trova site/room/rack
                Equipment::create([...$data, 'rack_id' => $rack?->id, 'tenant_id' => $tenantId]);
                $result->incrementCreated();
            } catch (Throwable $e) {
                $result->addError($rowIdx + 2, [$e->getMessage()]);
            }
        }
        if ($result->hasErrors() && ! $this->ignoreErrors) {
            throw new ImportFailedException($result);
        }
    });
    return $result;
}
```

### Validazioni import
- `type` deve essere uno dei valori dell'enum
- Se `mounted=true`: site/room/rack presenti e univoci, U disponibili
- Serial unico per tenant (warning se duplicato, opzione "merge" o "skip")
- Asset tag unico per tenant

### Template CSV
Bottone "Scarica template" che fornisce un CSV con solo le intestazioni e una riga di esempio.

## Pagina dedicata `/imports/equipment`
- Mostra storico import con: data, utente, file, righe importate, errori
- Click su un import storico → mostra report dettagliato
- Tabella `imports` con: id, tenant_id, user_id, type, file_path, status, summary (json)

## Test

```php
it('exports rack as PDF')  // genera file e verifica esistenza + size > 1KB
it('exports equipment list as CSV')  // verifica header + count righe
it('imports equipment from valid CSV')
it('rolls back import on validation errors')
it('detects duplicate serials and reports them')
it('creates missing site/room/rack hierarchy on import')
```

## Definition of Done

- [ ] Esportazione PDF rack genera file valido, scaricabile
- [ ] Esportazione PNG topologia funziona da UI
- [ ] Esportazione CSV con tutti i dispositivi del tenant
- [ ] Template CSV scaricabile
- [ ] Import CSV con preview, validazione, transaction
- [ ] Storico import visibile
- [ ] Test verdi
- [ ] Job di cleanup file export schedulato
- [ ] Commit: `feat: phase 6 — export/import`

➡️ Procedi alla **FASE 7**.
