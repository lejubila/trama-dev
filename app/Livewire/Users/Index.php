<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Global user administration (admin only). Users have a single global role;
 * only clienti are assigned to specific tenants. The list spans every tenant.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'cliente';

    /** @var list<int> */
    public array $assignedTenantIds = [];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);
        $this->reset(['editingId', 'name', 'email', 'password', 'assignedTenantIds']);
        $this->role = 'cliente';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        /** @var User $target */
        $target = User::query()->findOrFail($id);
        $this->authorize('update', $target);

        $this->editingId = $target->getKey();
        $this->name = $target->name;
        $this->email = $target->email;
        $this->password = '';
        $this->role = $target->role->value;
        $this->assignedTenantIds = $target->tenants()->pluck('tenants.id')->all();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['showForm', 'editingId', 'name', 'email', 'password', 'assignedTenantIds']);
        $this->role = 'cliente';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $rules = [
            'name' => 'required|string|max:150',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'assignedTenantIds' => 'array',
            'assignedTenantIds.*' => 'integer|exists:tenants,id',
        ];

        $rules['password'] = $this->editingId === null
            ? 'required|string|min:8|max:255'
            : 'nullable|string|min:8|max:255';

        $data = $this->validate($rules);

        $role = UserRole::from($data['role']);
        // Tenant assignment only matters for clienti.
        $tenantIds = $role === UserRole::Cliente ? array_map('intval', $this->assignedTenantIds) : [];

        if ($this->editingId === null) {
            $this->authorize('create', User::class);

            DB::transaction(function () use ($data, $role, $tenantIds): void {
                /** @var User $user */
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $role,
                    'password' => Hash::make($data['password']),
                ]);

                $user->tenants()->sync($tenantIds);
            });

            $this->dispatch('toast', type: 'success', message: 'Utente creato.');
        } else {
            /** @var User $target */
            $target = User::query()->findOrFail($this->editingId);
            $this->authorize('update', $target);

            // Guard: do not demote the last remaining admin.
            if ($target->isAdmin() && $role !== UserRole::Admin && $this->isLastAdmin($target)) {
                $this->addError('role', 'Non puoi rimuovere il ruolo all\'ultimo amministratore.');

                return;
            }

            DB::transaction(function () use ($target, $data, $role, $tenantIds): void {
                $update = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $role,
                ];

                if (! empty($data['password'])) {
                    $update['password'] = Hash::make($data['password']);
                }

                $target->update($update);
                $target->tenants()->sync($tenantIds);
            });

            $this->dispatch('toast', type: 'success', message: 'Utente aggiornato.');
        }

        $this->cancel();
    }

    public function delete(int $id): void
    {
        /** @var User $target */
        $target = User::query()->findOrFail($id);
        $this->authorize('delete', $target);

        if ($target->getKey() === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Non puoi eliminare il tuo stesso account.');

            return;
        }

        if ($target->isAdmin() && $this->isLastAdmin($target)) {
            $this->dispatch('toast', type: 'error', message: 'Non puoi eliminare l\'ultimo amministratore.');

            return;
        }

        $target->delete();

        $this->dispatch('toast', type: 'success', message: 'Utente eliminato.');
    }

    public function render(): View
    {
        $users = User::query()
            ->with(['tenants:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return view('livewire.users.index', [
            'users' => $users,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function isLastAdmin(User $excluding): bool
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->whereKeyNot($excluding->getKey())
            ->doesntExist();
    }
}
