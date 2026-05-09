<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_tenant_id',
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
        ];
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role', 'created_at');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function roleInTenant(Tenant $tenant): ?string
    {
        $found = $this->tenants()
            ->where('tenants.id', $tenant->getKey())
            ->first();

        if ($found === null) {
            return null;
        }

        $role = $found->getRelationValue('pivot')?->getAttribute('role');

        return is_string($role) ? $role : null;
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()
            ->where('tenants.id', $tenant->getKey())
            ->exists();
    }

    public function hasRoleInCurrentTenant(string $role): bool
    {
        if ($this->current_tenant_id === null) {
            return false;
        }

        return $this->tenants()
            ->where('tenants.id', $this->current_tenant_id)
            ->wherePivot('role', $role)
            ->exists();
    }

    /**
     * @param  list<string>  $roles
     */
    public function hasAnyRoleInCurrentTenant(array $roles): bool
    {
        if ($this->current_tenant_id === null || $roles === []) {
            return false;
        }

        return $this->tenants()
            ->where('tenants.id', $this->current_tenant_id)
            ->wherePivotIn('role', $roles)
            ->exists();
    }
}
