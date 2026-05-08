# FASE 7 — REST API

> Obiettivo: API REST completa autenticata via Sanctum per integrazioni esterne
> (script di provisioning, monitoring, automazioni).

## Routing
File: `routes/api.php`. Tutto sotto prefisso `/api/v1`.

```php
Route::prefix('v1')->middleware(['auth:sanctum', 'tenant.required'])->group(function () {
    Route::apiResource('sites', SiteController::class);
    Route::apiResource('sites.rooms', RoomController::class);
    Route::apiResource('rooms.racks', RackController::class)->shallow();
    Route::apiResource('equipment', EquipmentController::class);
    Route::apiResource('equipment.interfaces', InterfaceController::class)->shallow();
    Route::apiResource('connections', ConnectionController::class);
    Route::get('topology', [TopologyController::class, 'show']);
});
```

## Middleware `EnsureTenantHeader`

```php
class EnsureTenantHeader
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (! $tenantId) {
            return response()->json(['error' => 'Missing X-Tenant-Id header'], 400);
        }
        $tenant = auth()->user()->tenants()->find($tenantId);
        if (! $tenant) {
            return response()->json(['error' => 'Forbidden tenant'], 403);
        }
        TenantContext::setId($tenant->id);
        return $next($request);
    }
}
```

Registralo come `tenant.required` in `bootstrap/app.php`.

## Token management

Endpoint `/api/v1/tokens`:
- `GET` lista token dell'utente
- `POST` crea nuovo token con `name` e `abilities` (es. `['read', 'write']`)
- `DELETE /{id}` revoca

UI: pagina `Settings → API Tokens` con generazione e visualizzazione one-time del token.

## Resources (trasformazione output)

`App\Http\Resources\EquipmentResource`:
```php
public function toArray($request): array
{
    return [
        'id'         => $this->id,
        'type'       => 'equipment',
        'attributes' => [
            'name'       => $this->name,
            'kind'       => $this->type->value,
            'vendor'     => $this->vendor,
            'model'      => $this->model,
            'serial'     => $this->serial,
            'mounted'    => $this->mounted,
            'position'   => $this->mounted ? [
                'rack_id'   => $this->rack_id,
                'u_start'   => $this->position_u_start,
                'u_height'  => $this->position_u_height,
            ] : null,
            'status'     => $this->status->value,
            'management_ip' => $this->management_ip,
            'updated_at' => $this->updated_at->toIso8601String(),
        ],
        'relationships' => [
            'interfaces' => InterfaceResource::collection($this->whenLoaded('interfaces')),
            'rack'       => new RackResource($this->whenLoaded('rack')),
        ],
        'links' => [
            'self' => route('api.v1.equipment.show', $this->id),
        ],
    ];
}
```

Risorse coerenti per tutte le entità.

## Form Requests

Una `FormRequest` per ogni endpoint che accetta input. Usa le rules condivise da
`App\Validation\*` create in fase 3.

```php
class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Equipment::class);
    }

    public function rules(): array
    {
        return EquipmentRules::rules();
    }
}
```

## Controllers

Sottili. Esempio:
```php
class EquipmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Equipment::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('site_id'), fn ($q) => $q->whereHas('rack.room', fn ($q) => $q->where('site_id', $request->site_id)))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'ilike', "%{$request->q}%"));

        return EquipmentResource::collection($query->paginate(min($request->per_page ?? 50, 200)));
    }

    public function store(StoreEquipmentRequest $request, EquipmentCreator $action): EquipmentResource
    {
        $eq = $action->execute($request->validated());
        return new EquipmentResource($eq);
    }

    public function show(Equipment $equipment): EquipmentResource
    {
        $this->authorize('view', $equipment);
        return new EquipmentResource($equipment->load('interfaces', 'rack'));
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): EquipmentResource
    {
        $equipment->update($request->validated());
        return new EquipmentResource($equipment);
    }

    public function destroy(Equipment $equipment): Response
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();
        return response()->noContent();
    }
}
```

## Endpoint topologia

`GET /api/v1/topology?site_id={id}`:
- Restituisce lo stesso JSON usato dalla vista Cytoscape
- Filtri: `site_id`, `types[]`, `vlan`, `status`

## Rate limiting

In `RouteServiceProvider`:
```php
RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?? $r->ip()));
```

## OpenAPI / Swagger

Annotare i controller con phpDoc OpenAPI o usare `darkaonline/l5-swagger`:
```bash
docker compose exec app php artisan l5-swagger:generate
```
UI disponibile su `/api/documentation`.

Esempio annotazione:
```php
/**
 * @OA\Get(
 *   path="/api/v1/equipment",
 *   tags={"Equipment"},
 *   security={{"sanctum":{}}},
 *   @OA\Parameter(name="X-Tenant-Id", in="header", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Lista equipment")
 * )
 */
```

## Webhook (bonus)

Tabella `webhooks` (tenant_id, url, secret, events[], active).
Job che invia payload con HMAC SHA256 quando avvengono eventi `equipment.created`, `equipment.updated`, `connection.created`, ecc.

> Marca come "stretch goal" — implementa solo se la fase 7 base è completa.

## Test

Per ogni endpoint, un Pest test:
```php
it('lists equipment for current tenant', function () {
    $tenant = Tenant::factory()->create();
    Equipment::factory()->count(5)->for($tenant)->create();
    Equipment::factory()->count(3)->create(); // altro tenant

    $user = User::factory()->withTenant($tenant, 'tecnico')->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Tenant-Id'   => $tenant->id,
        'Accept'        => 'application/json',
    ])->getJson('/api/v1/equipment');

    $response->assertOk()->assertJsonCount(5, 'data');
});
```

Test isolamento tenant, validazione, autorizzazione per ogni metodo.

## Definition of Done

- [ ] Tutti gli endpoint funzionanti, testati, documentati
- [ ] Swagger UI accessibile
- [ ] Token management UI
- [ ] Rate limit attivo
- [ ] Test fase 7 verdi (>= 40 test)
- [ ] Postman collection esportata in `docs/postman/trama.json`
- [ ] Commit: `feat: phase 7 — REST API`

➡️ Procedi alla **FASE 8**.
