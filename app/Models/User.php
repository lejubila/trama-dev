<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $current_tenant_id
 * @property array<string, mixed>|null $preferences
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'current_tenant_id',
        'preferences',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'role' => UserRole::class,
        ];
    }

    /**
     * Tenants the user is explicitly assigned to. Only meaningful for clienti;
     * admins and tecnici can access every tenant regardless of this pivot.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('created_at');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    /**
     * The user's preferred UI locale, or null if unset/unsupported. Stored in
     * the `preferences` JSON column so it sits alongside theme and friends.
     */
    public function preferredLocale(): ?string
    {
        $locale = data_get($this->preferences, 'locale');

        return is_string($locale) && in_array($locale, config('app.supported_locales', []), true)
            ? $locale
            : null;
    }

    /**
     * Persist the preferred UI locale, preserving the other preference keys.
     */
    public function setLocalePreference(string $locale): void
    {
        $this->preferences = array_merge($this->preferences ?? [], ['locale' => $locale]);
        $this->save();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isTecnico(): bool
    {
        return $this->role === UserRole::Tecnico;
    }

    public function isCliente(): bool
    {
        return $this->role === UserRole::Cliente;
    }

    /**
     * Admin and tecnico may create/update/delete tenant data.
     */
    public function canManageData(): bool
    {
        return $this->role->canManageData();
    }

    /**
     * Whether the user is allowed to operate within the given tenant: admins and
     * tecnici may access any tenant; clienti only the ones they are assigned to.
     */
    public function canAccessTenant(Tenant $tenant): bool
    {
        if ($this->canManageData()) {
            return true;
        }

        return $this->belongsToTenant($tenant);
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()
            ->where('tenants.id', $tenant->getKey())
            ->exists();
    }
}
