<?php

declare(strict_types=1);

namespace App\Livewire\Tenants;

use App\Actions\Tenancy\AssignTenant;
use App\Actions\Tenancy\RemoveMember;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Manage extends Component
{
    public Tenant $tenant;

    public string $activeTab = 'general';

    // General tab state
    public string $name = '';

    public string $slug = '';

    public string $domain = '';

    // Members tab state: clienti assigned to this tenant (no role).
    public ?int $newUserId = null;

    public function mount(Tenant $tenant): void
    {
        $this->authorize('view', $tenant);
        $this->tenant = $tenant;
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->domain = (string) ($tenant->domain ?? '');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['general', 'members'], true) ? $tab : 'general';
    }

    public function saveGeneral(): void
    {
        $this->authorize('update', $this->tenant);

        $data = $this->validate([
            'name' => 'required|string|max:150',
            'slug' => ['required', 'string', 'alpha_dash', 'max:80', Rule::unique('tenants', 'slug')->ignore($this->tenant->getKey())],
            'domain' => 'nullable|string|max:255',
        ]);

        $this->tenant->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] !== '' ? $data['domain'] : null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Cliente aggiornato.');
    }

    public function deleteTenant(): void
    {
        $this->authorize('delete', $this->tenant);

        $name = $this->tenant->name;
        $this->tenant->delete();

        // The acting user might have just blown up their current_tenant_id;
        // SetCurrentTenant middleware will bootstrap to the next available one
        // on the redirect.
        TenantContext::clear();

        $this->dispatch('toast', type: 'success', message: "Cliente \"{$name}\" eliminato.");
        $this->redirectRoute('tenants.index', navigate: true);
    }

    public function addMember(AssignTenant $action): void
    {
        $this->authorize('update', $this->tenant);

        $this->validate([
            'newUserId' => 'required|integer|exists:users,id',
        ]);

        /** @var User $user */
        $user = User::query()->findOrFail($this->newUserId);

        try {
            $action->execute($this->tenant, $user);
        } catch (InvalidArgumentException $e) {
            $this->addError('newUserId', $e->getMessage());

            return;
        }

        $this->reset(['newUserId']);
        $this->dispatch('toast', type: 'success', message: 'Cliente assegnato.');
    }

    public function removeMember(int $userId, RemoveMember $action): void
    {
        $this->authorize('update', $this->tenant);

        $member = User::query()->findOrFail($userId);

        try {
            $action->execute($this->tenant, $member);
        } catch (InvalidArgumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Assegnazione rimossa.');
    }

    public function render(): View
    {
        $memberIds = $this->tenant->users()->pluck('users.id');

        return view('livewire.tenants.manage', [
            'members' => $this->tenant->users()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email']),
            // Only clienti can be assigned to a tenant (admins/tecnici see all).
            // Candidates = cliente users not yet assigned here.
            'candidates' => User::query()
                ->where('role', UserRole::Cliente)
                ->whereNotIn('id', $memberIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }
}
