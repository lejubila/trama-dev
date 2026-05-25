<?php

declare(strict_types=1);

namespace App\Livewire\Equipment;

use App\Actions\Interfaces\CreateKeystonePair;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceSide;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Equipment $equipment;

    #[Url(as: 'tab', except: 'general')]
    public string $activeTab = 'general';

    // Interface form state
    public bool $showIfForm = false;

    public ?int $editingIfId = null;

    public string $ifName = '';

    public string $ifType = 'ethernet';

    public string $ifMedia = 'copper';

    public string $ifConnector = '';

    public ?int $ifSpeedMbps = 1000;

    public string $ifVlanMode = 'access';

    public ?int $ifVlanDefault = 1;

    /**
     * User-edited representation of `vlans_allowed`: a comma-separated list
     * with optional ranges, e.g. "1, 60, 100-105". Parsed on save into a
     * sorted/deduped array of integers in `parseVlanListOrNull`.
     */
    public string $ifVlansAllowedText = '';

    public string $ifIpAddress = '';

    public string $ifMacAddress = '';

    public string $ifStatus = 'unknown';

    public string $ifPoe = 'none';

    public string $ifDescription = '';

    // ── Bulk creation (visible only when creating, not editing) ──────────
    public bool $ifBulk = false;

    // Nullable so clearing the field (Livewire sends "") doesn't leave the
    // typed property uninitialized and crash the live preview.
    public ?int $ifBulkQuantity = 12;

    public ?int $ifBulkStartFrom = 1;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $base = [
            'ifType' => ['required', Rule::in(array_column(InterfaceType::cases(), 'value'))],
            'ifMedia' => ['required', Rule::in(array_column(InterfaceMedia::cases(), 'value'))],
            'ifConnector' => 'nullable|string|max:20',
            'ifSpeedMbps' => 'nullable|integer|min:1',
            'ifVlanMode' => ['nullable', Rule::in(array_column(InterfaceVlanMode::cases(), 'value'))],
            'ifVlanDefault' => 'nullable|integer|min:1|max:4094',
            'ifVlansAllowedText' => 'nullable|string|max:255',
            'ifIpAddress' => 'nullable|string|max:45',
            'ifMacAddress' => 'nullable|string|max:17',
            'ifStatus' => ['required', Rule::in(array_column(InterfaceStatus::cases(), 'value'))],
            'ifPoe' => ['required', Rule::in(array_column(InterfacePoe::cases(), 'value'))],
            'ifDescription' => 'nullable|string|max:255',
        ];

        // In bulk mode the name is a *prefix*; the unicità is checked per
        // generated suffix inside saveBulkIf(), not at validate time.
        $base['ifName'] = $this->ifBulk && $this->editingIfId === null
            ? 'required|string|max:60'
            : [
                'required', 'string', 'max:80',
                Rule::unique('interfaces', 'name')
                    ->where('equipment_id', $this->equipment->getKey())
                    ->ignore($this->editingIfId),
            ];

        if ($this->ifBulk && $this->editingIfId === null) {
            $base['ifBulkQuantity'] = 'required|integer|min:2|max:256';
            $base['ifBulkStartFrom'] = 'required|integer|min:0|max:9999';
        }

        return $base;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ifName.unique' => 'Esiste già un\'interfaccia con questo nome su questo dispositivo.',
        ];
    }

    public function mount(Equipment $equipment): void
    {
        $this->authorize('view', $equipment);
        $this->equipment = $equipment->load('rack.room.site');

        // Sanitize tab param coming from the URL.
        if (! in_array($this->activeTab, ['general', 'interfaces', 'connections', 'audit'], true)) {
            $this->activeTab = 'general';
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['general', 'interfaces', 'connections', 'audit'], true) ? $tab : 'general';
    }

    public function openIfCreate(): void
    {
        $this->authorize('create', NetworkInterface::class);
        $this->reset(['editingIfId', 'ifName', 'ifIpAddress', 'ifMacAddress', 'ifDescription', 'ifBulk', 'ifVlansAllowedText']);
        $this->ifBulkQuantity = 12;
        $this->ifBulkStartFrom = 1;
        $isPatchLike = in_array(
            $this->equipment->type,
            [EquipmentType::PatchPanel, EquipmentType::WallOutlet],
            true,
        );
        $this->ifType = $isPatchLike ? 'keystone' : 'ethernet';
        $this->ifMedia = 'copper';
        $this->ifConnector = $isPatchLike ? 'rj45' : '';
        $this->ifSpeedMbps = $isPatchLike ? null : 1000;
        $this->ifVlanMode = 'access';
        $this->ifVlanDefault = 1;
        $this->ifStatus = 'unknown';
        $this->ifPoe = 'none';
        $this->resetErrorBag();
        $this->showIfForm = true;
    }

    public function openIfEdit(int $id): void
    {
        $if = NetworkInterface::query()->findOrFail($id);
        $this->authorize('update', $if);

        $this->ifBulk = false; // bulk mode is creation-only
        $this->editingIfId = $if->getKey();
        $this->ifName = $if->name;
        $this->ifType = $if->type?->value ?? 'ethernet';
        $this->ifMedia = $if->media?->value ?? 'copper';
        $this->ifConnector = (string) ($if->connector ?? '');
        $this->ifSpeedMbps = $if->speed_mbps;
        $this->ifVlanMode = $if->vlan_mode?->value ?? 'access';
        $this->ifVlanDefault = $if->vlan_default;
        $this->ifVlansAllowedText = $this->formatVlanRanges(
            is_array($if->vlans_allowed) ? $if->vlans_allowed : null
        );
        $this->ifIpAddress = (string) ($if->ip_address ?? '');
        $this->ifMacAddress = (string) ($if->mac_address ?? '');
        $this->ifStatus = $if->status?->value ?? 'unknown';
        $this->ifPoe = $if->poe?->value ?? 'none';
        $this->ifDescription = (string) ($if->description ?? '');
        $this->resetErrorBag();
        $this->showIfForm = true;
    }

    public function saveIf(): void
    {
        if ($this->ifBulk && $this->editingIfId === null) {
            $this->saveBulkIf();

            return;
        }

        $this->saveSingleIf();
    }

    private function saveSingleIf(): void
    {
        $this->validate();

        if (! $this->validateVlansAllowed()) {
            return;
        }

        if ($this->editingIfId !== null) {
            $if = NetworkInterface::query()->findOrFail($this->editingIfId);
            $this->authorize('update', $if);
            $payload = array_merge($this->basePayload(), ['name' => $this->ifName]);
            $if->update($payload);
            $this->dispatch('toast', type: 'success', message: 'Interfaccia aggiornata.');
            $this->showIfForm = false;

            return;
        }

        $this->authorize('create', NetworkInterface::class);

        if ($this->shouldCreateAsKeystonePair()) {
            app(CreateKeystonePair::class)->execute($this->equipment, [
                'name' => $this->ifName,
                'connector' => $this->ifConnector !== '' ? $this->ifConnector : null,
                'description' => $this->ifDescription !== '' ? $this->ifDescription : null,
                'status' => $this->ifStatus,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Porta creata (front + rear).');
        } else {
            NetworkInterface::create(array_merge($this->basePayload(), ['name' => $this->ifName]));
            $this->dispatch('toast', type: 'success', message: 'Interfaccia creata.');
        }

        $this->showIfForm = false;
    }

    private function saveBulkIf(): void
    {
        $this->authorize('create', NetworkInterface::class);
        $this->validate();

        if (! $this->validateVlansAllowed()) {
            return;
        }

        $names = $this->generateBulkNames();

        // Pre-check uniqueness in batch — friendlier than a DB exception
        // (and we'd still hit the unique partial index if a parallel write
        // landed in between, but the window is tiny).
        $existing = NetworkInterface::query()
            ->where('equipment_id', $this->equipment->getKey())
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        if ($existing !== []) {
            $this->addError('ifName', 'Conflitto: '.implode(', ', $existing).' già presenti.');

            return;
        }

        if ($this->shouldCreateAsKeystonePair()) {
            $action = app(CreateKeystonePair::class);
            DB::transaction(function () use ($names, $action): void {
                foreach ($names as $name) {
                    $action->execute($this->equipment, [
                        'name' => $name,
                        'connector' => $this->ifConnector !== '' ? $this->ifConnector : null,
                        'description' => $this->ifDescription !== '' ? $this->ifDescription : null,
                        'status' => $this->ifStatus,
                    ]);
                }
            });
            $this->dispatch('toast', type: 'success', message: count($names).' porte create (front + rear).');
        } else {
            $base = $this->basePayload();
            DB::transaction(function () use ($names, $base): void {
                foreach ($names as $name) {
                    NetworkInterface::create(array_merge($base, ['name' => $name]));
                }
            });
            $this->dispatch('toast', type: 'success', message: count($names).' interfacce create.');
        }

        $this->showIfForm = false;
    }

    /**
     * Pair-creation is implicit for keystone ports on patch panels and wall
     * outlets — every "port" is physically two endpoints (front + rear).
     */
    private function shouldCreateAsKeystonePair(): bool
    {
        if ($this->ifType !== InterfaceType::Keystone->value) {
            return false;
        }

        return in_array(
            $this->equipment->type,
            [EquipmentType::PatchPanel, EquipmentType::WallOutlet],
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'equipment_id' => $this->equipment->getKey(),
            'type' => InterfaceType::from($this->ifType),
            'media' => InterfaceMedia::from($this->ifMedia),
            'connector' => $this->ifConnector !== '' ? $this->ifConnector : null,
            'speed_mbps' => $this->ifSpeedMbps,
            'vlan_mode' => InterfaceVlanMode::from($this->ifVlanMode),
            // VLAN default and allowed are meaningless on ports with no VLAN
            // concept (none) or on transparent passthroughs (any tag flows
            // unchanged): force them to null no matter what the user typed.
            'vlan_default' => $this->vlanFieldsDisabled() ? null : $this->ifVlanDefault,
            // `vlans_allowed` is meaningful only for tagged trunks/hybrids.
            // Access ports carry a single VLAN (vlan_default); none and
            // transparent have no VLAN logic at all → blank in every case.
            'vlans_allowed' => $this->vlansAllowedDisabled()
                ? null
                : $this->parseVlanListOrNull($this->ifVlansAllowedText),
            'ip_address' => $this->ifIpAddress !== '' ? $this->ifIpAddress : null,
            'mac_address' => $this->ifMacAddress !== '' ? $this->ifMacAddress : null,
            'status' => InterfaceStatus::from($this->ifStatus),
            'poe' => InterfacePoe::from($this->ifPoe),
            'description' => $this->ifDescription !== '' ? $this->ifDescription : null,
        ];
    }

    /**
     * Generates the names list for a bulk create. The user-provided
     * `ifName` is a template where the `%` character marks where the
     * zero-padded number is to be inserted. `%%` is a literal escape for
     * a percent sign. When no `%` appears at all, the number is appended
     * at the end (backward-compatible with the old "prefix-only" mode).
     *
     * Pad length is the digit count of the LARGEST number in the range
     * (start..start+quantity-1), so start=95/qty=10 yields 95..104 with
     * width 3 → 095, 096, …, 104.
     *
     * @return list<string>
     */
    private function generateBulkNames(): array
    {
        $start = max(0, (int) $this->ifBulkStartFrom);
        $quantity = max(1, min(256, (int) $this->ifBulkQuantity));
        $max = $start + $quantity - 1;
        $padLen = strlen((string) $max);

        // Tolerate `%%` as a literal percent sign by parking it under a
        // sentinel before checking for an actual `%` placeholder.
        $sentinel = "\0";
        $template = str_replace('%%', $sentinel, $this->ifName);
        $hasPlaceholder = str_contains($template, '%');

        $names = [];
        for ($i = 0; $i < $quantity; $i++) {
            $num = str_pad((string) ($start + $i), $padLen, '0', STR_PAD_LEFT);
            $name = $hasPlaceholder
                ? str_replace('%', $num, $template)
                : $template.$num;
            $names[] = str_replace($sentinel, '%', $name);
        }

        return $names;
    }

    /**
     * Sample preview of the generated bulk names for the form: first 3 +
     * last 1 if the list is longer than 4 — used only in the Blade.
     *
     * @return list<string>
     */
    public function previewBulkNames(): array
    {
        if (! $this->ifBulk || $this->ifName === '') {
            return [];
        }
        $all = $this->generateBulkNames();
        if (count($all) <= 4) {
            return $all;
        }

        return [$all[0], $all[1], $all[2], '…', end($all)];
    }

    public function deleteIf(int $id): void
    {
        $if = NetworkInterface::query()->findOrFail($id);
        $this->authorize('delete', $if);
        $if->delete();
        $this->dispatch('toast', type: 'success', message: 'Interfaccia rimossa.');
    }

    public function deleteConnection(int $id): void
    {
        $c = Connection::query()->findOrFail($id);
        $this->authorize('delete', $c);
        $c->delete();
        $this->dispatch('toast', type: 'success', message: 'Connessione rimossa.');
    }

    /**
     * Runs parseVlanListOrNull as a guard before persisting and surfaces a
     * friendly error under the input. Returns false when the syntax is
     * invalid (caller short-circuits the save).
     */
    private function validateVlansAllowed(): bool
    {
        // Skip the parser when the field is going to be wiped anyway
        // (none/access/transparent — see vlansAllowedDisabled).
        if ($this->vlansAllowedDisabled()) {
            return true;
        }
        try {
            $this->parseVlanListOrNull($this->ifVlansAllowedText);

            return true;
        } catch (\InvalidArgumentException $e) {
            $this->addError('ifVlansAllowedText', $e->getMessage());

            return false;
        }
    }

    /**
     * True when the active VLAN mode makes both `vlan_default` and
     * `vlans_allowed` meaningless: `none` (no VLAN concept at all) and
     * `transparent` (any tag flows through unchanged).
     */
    private function vlanFieldsDisabled(): bool
    {
        return in_array($this->ifVlanMode, ['none', 'transparent'], true);
    }

    /**
     * True when `vlans_allowed` does not apply: in addition to the modes
     * above (none/transparent), access ports also have no allowed-list —
     * they carry a single VLAN (vlan_default).
     */
    private function vlansAllowedDisabled(): bool
    {
        return in_array($this->ifVlanMode, ['none', 'access', 'transparent'], true);
    }

    /**
     * Parse a Cisco-style VLAN list like "1, 60, 100-105" into a sorted
     * deduped integer array. Empty input returns null. Throws
     * InvalidArgumentException on any malformed token (non-numeric, out of
     * range 1-4094, or descending range like "10-9").
     *
     * @return list<int>|null
     */
    private function parseVlanListOrNull(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $out = [];
        foreach (explode(',', $text) as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            if (str_contains($token, '-')) {
                $parts = explode('-', $token, 2);
                $from = trim($parts[0]);
                $to = trim($parts[1]);
                if (! ctype_digit($from) || ! ctype_digit($to)) {
                    throw new \InvalidArgumentException("Range non valido: \"{$token}\". Usa numeri 1-4094.");
                }
                $from = (int) $from;
                $to = (int) $to;
                if ($from < 1 || $to < 1 || $from > 4094 || $to > 4094) {
                    throw new \InvalidArgumentException("VLAN fuori intervallo 1-4094: \"{$token}\".");
                }
                if ($from > $to) {
                    throw new \InvalidArgumentException("Range invertito: \"{$token}\" (atteso \"from-to\" crescente).");
                }
                for ($v = $from; $v <= $to; $v++) {
                    $out[$v] = true;
                }
            } else {
                if (! ctype_digit($token)) {
                    throw new \InvalidArgumentException("Token non valido: \"{$token}\". Usa numeri 1-4094.");
                }
                $v = (int) $token;
                if ($v < 1 || $v > 4094) {
                    throw new \InvalidArgumentException("VLAN fuori intervallo 1-4094: \"{$token}\".");
                }
                $out[$v] = true;
            }
        }

        if ($out === []) {
            return null;
        }
        $vlans = array_keys($out);
        sort($vlans);

        return $vlans;
    }

    /**
     * Inverse of parseVlanListOrNull: compact "[1,2,3,5,6]" into "1-3, 5-6".
     * Public so the Blade view can render the value inline in the table.
     */
    public function formatVlanRanges(?array $vlans): string
    {
        if ($vlans === null || $vlans === []) {
            return '';
        }
        $vlans = array_values(array_unique(array_map('intval', $vlans)));
        sort($vlans);

        $ranges = [];
        $start = $vlans[0];
        $prev = $start;
        foreach (array_slice($vlans, 1) as $v) {
            if ($v === $prev + 1) {
                $prev = $v;

                continue;
            }
            $ranges[] = $start === $prev ? (string) $start : $start.'-'.$prev;
            $start = $v;
            $prev = $v;
        }
        $ranges[] = $start === $prev ? (string) $start : $start.'-'.$prev;

        return implode(', ', $ranges);
    }

    public function render(): View
    {
        $interfaceIds = $this->equipment->interfaces()->pluck('id');

        $connections = Connection::query()
            ->with([
                'fromInterface.equipment',
                'toInterface.equipment',
                'tags',
            ])
            ->where(function ($q) use ($interfaceIds): void {
                $q->whereIn('from_interface_id', $interfaceIds)
                    ->orWhereIn('to_interface_id', $interfaceIds);
            })
            ->orderByDesc('id')
            ->get();

        // For patch panels and wall outlets the listing groups the two halves
        // of each keystone port under the front row (the rear is reached via
        // the `paired` relation in the view).
        $interfaces = $this->equipment->interfaces()
            ->with('paired')
            ->where(function ($q): void {
                $q->whereNull('side')->orWhere('side', InterfaceSide::Front->value);
            })
            ->orderBy('index')
            ->orderBy('name')
            ->get();

        return view('livewire.equipment.show', [
            'interfaces' => $interfaces,
            'connections' => $connections,
            'audits' => $this->equipment->audits()->latest()->limit(50)->get(),
            'isPatchLike' => in_array(
                $this->equipment->type,
                [EquipmentType::PatchPanel, EquipmentType::WallOutlet],
                true,
            ),
        ]);
    }
}
