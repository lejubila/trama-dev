# FASE 1 — Auth & Multi-tenancy

> Obiettivo: utenti autenticati che possono appartenere a più tenant, switch tenant via UI,
> tutti i dati di dominio scoped per `tenant_id`.

## Approccio

Useremo una **soluzione custom semplice** invece di stancl/tenancy completo per la fase 1
(meno overhead, controllo totale): un trait `BelongsToTenant` + un global scope.

> Quando l'app sarà più matura, valutare la migrazione a stancl/tenancy se servono feature
> avanzate (subdomain isolation, DB-per-tenant). Per ora KISS.

## Task

### 1. Migration `tenants`
```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('slug', 80)->unique();
    $table->string('domain')->nullable();
    $table->jsonb('settings')->default('{}');
    $table->timestamps();
});
```

### 2. Aggiungi `current_tenant_id` a `users`
Migration `add_current_tenant_id_to_users_table`:
```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('current_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
});
```

### 3. Pivot `tenant_user`
```php
Schema::create('tenant_user', function (Blueprint $table) {
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role', 20); // admin / tecnico / cliente
    $table->timestamp('created_at')->useCurrent();
    $table->primary(['tenant_id', 'user_id']);
});
```

### 4. Model `Tenant`
- relazione `users()` belongsToMany con `withPivot('role')`
- factory base
- cast `settings => 'array'`

### 5. Aggiorna `User`
- relazione `tenants()` belongsToMany con role
- relazione `currentTenant()` belongsTo
- metodo `roleInTenant(Tenant $t): ?string`
- metodo `belongsToTenant(Tenant $t): bool`

### 6. Trait `App\Models\Concerns\BelongsToTenant`

```php
namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id && ($tenantId = TenantContext::id())) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
```

### 7. Global scope `App\Models\Scopes\TenantScope`
Filtra `WHERE tenant_id = TenantContext::id()`. Se il context è null (es. comando artisan)
→ non applica filtro (utile per maintenance, ma loggare warning).

### 8. Helper `App\Support\Tenancy\TenantContext`

```php
class TenantContext
{
    protected static ?int $tenantId = null;

    public static function setId(?int $id): void { static::$tenantId = $id; }
    public static function id(): ?int { return static::$tenantId; }
    public static function clear(): void { static::$tenantId = null; }
}
```

### 9. Middleware `SetCurrentTenant`
- Se utente loggato e ha `current_tenant_id` → `TenantContext::setId(...)`
- Se `current_tenant_id` non è tra i tenant dell'utente → reset a null e redirect alla pagina di selezione tenant
- Registra il middleware nel gruppo `web` (dopo `Authenticate`)

### 10. Rotta `POST /tenant/switch/{tenant}`
- Valida che l'utente appartenga al tenant
- Aggiorna `current_tenant_id`
- Redirect intended

### 11. UI: Selettore tenant
Componente Livewire `TenantSelector` che mostra dropdown con i tenant dell'utente e bottone
"cambia". Includilo nella topbar del layout.

### 12. Auth: registrazione e login
Laravel 11 non ha più `php artisan ui` di default. Usiamo **Laravel Breeze con Livewire**:
```bash
docker compose exec app composer require laravel/breeze --dev
docker compose exec app php artisan breeze:install livewire --no-interaction --pest
docker compose exec app php artisan migrate
docker compose exec app npm install && npm run build
```

### 13. Permessi (spatie/laravel-permission)
Configurare `team_keys` con `tenant_id` come team key.
- File `config/permission.php`: imposta `'teams' => true`, `'team_foreign_key' => 'tenant_id'`
- Permission seeder con permessi base: `manage_users`, `manage_equipment`, `manage_connections`, `view_only`
- Role seeder: `admin`, `tecnico`, `cliente` con i permessi appropriati per ogni tenant

### 14. Audit (owen-it/laravel-auditing)
- Configurare `config/audit.php` per registrare anche `tenant_id`
- I model che useremo aggiungeranno il trait `Auditable` (in fase 2)

### 15. Seeder demo
`DatabaseSeeder` deve creare:
- Tenant "ACME Spa" e "Beta Srl"
- 3 utenti (admin/tecnico/cliente) ciascuno appartenente a entrambi i tenant
- Permessi e ruoli assegnati

```php
// pseudocodice
$acme = Tenant::factory()->create(['name' => 'ACME Spa', 'slug' => 'acme']);
$beta = Tenant::factory()->create(['name' => 'Beta Srl', 'slug' => 'beta']);

$admin = User::factory()->create(['email' => 'admin@demo.test']);
$admin->tenants()->attach([$acme->id => ['role' => 'admin'], $beta->id => ['role' => 'admin']]);
$admin->update(['current_tenant_id' => $acme->id]);
// ... idem tecnico/cliente
```

### 16. Test multi-tenancy (Pest)
Test fondamentali da scrivere SUBITO:
- `it('isolates data between tenants')` — crea record con tenant A, login come utente del tenant B, non li vede
- `it('blocks tenant switch if user does not belong')` — tentativo di cambio tenant non autorizzato → 403
- `it('auto-fills tenant_id on model create')` — quando il context è settato, il record viene creato con il tenant_id corretto
- `it('allows admin role to access user management')` — gate test
- `it('denies cliente role from creating equipment')` — gate test

Tutti i test che lavorano con dati di dominio devono usare un helper `actingAsInTenant($user, $tenant)`
che logga l'utente E setta il TenantContext.

## Definition of Done

- [ ] Migrations creano `tenants`, `tenant_user`, aggiunge `current_tenant_id` a users
- [ ] Login funziona, dopo login si vede topbar con selettore tenant
- [ ] Switch tenant funziona e persiste
- [ ] Global scope filtra correttamente (provato manualmente in tinker)
- [ ] Tutti i test della suite di fase 1 passano
- [ ] Seeder crea l'ambiente demo descritto in README
- [ ] Commit: `feat: phase 1 — auth & multi-tenancy`

➡️ Procedi alla **FASE 2**.
