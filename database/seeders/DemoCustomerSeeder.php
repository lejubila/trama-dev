<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Interfaces\CreateKeystonePair;
use App\Actions\Vpn\AttachRemoteClient;
use App\Actions\WifiNetworks\AttachClient;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Enums\VpnProtocol;
use App\Enums\VpnRoutingMode;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\VpnRemoteAccess;
use App\Models\WifiNetwork;
use App\Services\ConnectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The fictional customer shown in the public demo: a small professional
 * firm with a server room and an open-space office, wired through a patch
 * panel and wall outlets. Deterministic (no Faker) so it also runs on a
 * --no-dev install; database/demo/*.zip is exported from it.
 *
 * VLANs: 10 uffici · 20 server · 30 ospiti · 99 management.
 */
class DemoCustomerSeeder extends Seeder
{
    public const SLUG = 'rossetti-partners';

    /** @var array<string, NetworkInterface> "EQUIPMENT:iface" => interface */
    private array $ifaces = [];

    public function __construct(
        private readonly ConnectionService $connections,
        private readonly CreateKeystonePair $keystones,
        private readonly AttachClient $wifiClients,
        private readonly AttachRemoteClient $vpnClients,
    ) {}

    public function run(): void
    {
        if (Tenant::query()->where('slug', self::SLUG)->exists()) {
            throw new RuntimeException('The demo customer "'.self::SLUG.'" already exists: delete it first.');
        }

        $previous = TenantContext::id();
        $tenant = Tenant::query()->create([
            'name' => 'Studio Rossetti & Partners',
            'slug' => self::SLUG,
            'settings' => [],
        ]);
        TenantContext::setId((int) $tenant->getKey());

        try {
            $this->build();
        } finally {
            TenantContext::setId($previous);
        }
    }

    private function build(): void
    {
        $site = Site::query()->create([
            'name' => 'Sede Firenze',
            'address' => 'Via dei Calzaiuoli 12, 50122 Firenze',
            'latitude' => 43.7714,
            'longitude' => 11.2546,
            'notes' => 'Studio professionale, 12 postazioni. Fibra FTTH 1 Gbit/s.',
        ]);

        $ced = Room::query()->create([
            'site_id' => $site->getKey(),
            'name' => 'CED',
            'floor' => 'Piano terra',
            'width_m' => 4,
            'depth_m' => 3,
            'rack_icon_size_px' => 40,
            'floor_plan_drawing' => $this->rectangle(4, 3, doors: [0.62], windows: []),
            'notes' => 'Locale tecnico climatizzato, accesso con badge.',
        ]);

        $office = Room::query()->create([
            'site_id' => $site->getKey(),
            'name' => 'Ufficio open space',
            'floor' => 'Piano 1',
            'width_m' => 10,
            'depth_m' => 8,
            'floor_plan_drawing' => $this->rectangle(10, 8, doors: [0.92], windows: [0.08, 0.17, 0.33, 0.42]),
            'notes' => 'Open space con 3 postazioni fisse, sala riunioni e area stampa.',
        ]);

        $rack = Rack::query()->create([
            'room_id' => $ced->getKey(),
            'name' => 'RACK-01',
            'height_units' => 24,
            'width_mm' => 600,
            'depth_mm' => 800,
            'position_x' => 2.0,
            'position_y' => 0.8,
            'notes' => 'Rack 19" 24U a pavimento, chiuso a chiave.',
        ]);

        $this->ced($rack, $ced);
        $this->office($office);
        $this->cabling();
        $this->wireless($site);
        $this->vpn();
        $this->tags();
    }

    // ── CED ───────────────────────────────────────────────────────────────

    private function ced(Rack $rack, Room $ced): void
    {
        $pp = $this->mounted($rack, EquipmentType::PatchPanel, 'PP-01', 24, 1, 'Panduit', 'CPP24FMWBLY', [
            'description' => 'Patch panel 24 porte Cat6, cablaggio verso le prese dell\'ufficio.',
        ]);
        for ($i = 1; $i <= 24; $i++) {
            $this->keystonePair($pp, sprintf('P%02d', $i), $i);
        }

        $rtr = $this->mounted($rack, EquipmentType::Router, 'RTR-INTERNET', 22, 1, 'Zyxel', 'EX5601-T0', [
            'firmware' => 'V5.70(ACDZ.0)C0',
            'management_ip' => '192.168.1.1',
            'description' => 'Router dell\'operatore (FTTH 1000/300). Gestito dal provider.',
        ]);
        $this->iface($rtr, 'WAN', ['type' => InterfaceType::Fiber, 'media' => InterfaceMedia::Fiber, 'connector' => 'SC', 'ip_address' => '203.0.113.42/30', 'description' => 'FTTH – PPPoE, IP statico']);
        $this->iface($rtr, 'LAN1', ['ip_address' => '192.168.1.1/24', 'description' => 'Verso firewall']);

        $fw = $this->mounted($rack, EquipmentType::Firewall, 'FW-01', 21, 1, 'Fortinet', 'FortiGate 60F', [
            'serial' => 'FGT60FTK22091873',
            'firmware' => 'FortiOS 7.4.4',
            'management_ip' => '10.0.99.1',
            'description' => 'Firewall perimetrale: routing tra VLAN, NAT, VPN remote access.',
        ]);
        $this->iface($fw, 'wan1', ['ip_address' => '192.168.1.2/24', 'description' => 'Uplink verso router operatore']);
        $this->iface($fw, 'wan2', ['status' => InterfaceStatus::Down, 'description' => 'Riservata a seconda linea']);
        $this->iface($fw, 'internal1', ['vlan_mode' => InterfaceVlanMode::Trunk, 'vlan_default' => 99, 'vlans_allowed' => [10, 20, 30, 99], 'ip_address' => '10.0.99.1/24', 'description' => 'Trunk verso SW-CORE']);
        $this->iface($fw, 'dmz', ['status' => InterfaceStatus::Down]);
        foreach ([10 => 'Uffici', 20 => 'Server', 30 => 'Ospiti'] as $vlan => $label) {
            $this->iface($fw, "vlan{$vlan}", [
                'type' => InterfaceType::Virtual, 'media' => InterfaceMedia::Virtual, 'speed_mbps' => null,
                'vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => $vlan,
                'ip_address' => "10.0.{$vlan}.1/24", 'description' => "Gateway VLAN {$vlan} – {$label}",
            ]);
        }

        $sw = $this->mounted($rack, EquipmentType::Switch, 'SW-CORE', 20, 1, 'HPE Aruba', '2930F 24G PoE+ 4SFP', [
            'serial' => 'CN13KXZ0QH',
            'firmware' => 'WC.16.11.0016',
            'management_ip' => '10.0.99.2',
            'description' => 'Switch di core, PoE+ per access point.',
        ]);
        for ($i = 1; $i <= 24; $i++) {
            [$mode, $default, $allowed, $desc] = match (true) {
                $i === 1 => [InterfaceVlanMode::Trunk, 99, [10, 20, 30, 99], 'Uplink FW-01'],
                $i === 2 => [InterfaceVlanMode::Access, 99, null, 'PVE-01 management'],
                $i === 3 => [InterfaceVlanMode::Trunk, 20, [10, 20], 'PVE-01 trunk VM'],
                $i === 4 => [InterfaceVlanMode::Access, 20, null, 'NAS-01'],
                $i === 5 => [InterfaceVlanMode::Access, 99, null, 'UPS-01 scheda di rete'],
                $i === 15 => [InterfaceVlanMode::Trunk, 99, [10, 30, 99], 'AP-01 (PoE)'],
                default => [InterfaceVlanMode::Access, 10, null, null],
            };
            $this->iface($sw, (string) $i, [
                'index' => $i,
                'vlan_mode' => $mode,
                'vlan_default' => $default,
                'vlans_allowed' => $allowed,
                'poe' => InterfacePoe::Pse,
                'status' => ($i <= 5 || ($i >= 10 && $i <= 15)) ? InterfaceStatus::Up : InterfaceStatus::Down,
                'description' => $desc,
            ]);
        }

        $pve = $this->mounted($rack, EquipmentType::Hypervisor, 'PVE-01', 16, 2, 'Dell', 'PowerEdge R650xs', [
            'serial' => '7XK2LM3',
            'firmware' => 'Proxmox VE 8.2',
            'management_ip' => '10.0.99.10',
            'custom_fields' => ['hypervisor_vendor' => 'proxmox'],
            'description' => 'Server di virtualizzazione: 2× Xeon Silver 4410Y, 128 GB RAM, 4× SSD 1,92 TB in RAID 10.',
        ]);
        $this->iface($pve, 'eno1', ['vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 99, 'ip_address' => '10.0.99.10/24', 'description' => 'Management Proxmox']);
        $this->iface($pve, 'eno2', ['vlan_mode' => InterfaceVlanMode::Trunk, 'vlan_default' => 20, 'vlans_allowed' => [10, 20], 'description' => 'Bridge vmbr1 per le VM']);
        $this->iface($pve, 'idrac', ['type' => InterfaceType::Management, 'status' => InterfaceStatus::Down, 'description' => 'iDRAC, non cablata']);

        $vms = [
            ['DC-01', 'Windows Server 2022 Standard', 4, 8192, 120, '10.0.20.10', 'Domain controller Active Directory, DNS e DHCP.'],
            ['FILESRV-01', 'Windows Server 2022 Standard', 4, 8192, 2048, '10.0.20.11', 'File server con le cartelle condivise dello studio.'],
            ['GESTIONALE', 'Ubuntu Server 24.04 LTS', 8, 32768, 500, '10.0.20.12', 'Gestionale di studio e database PostgreSQL.'],
            ['BACKUP-01', 'Proxmox Backup Server 3.2', 2, 4096, 64, '10.0.20.13', 'Backup notturno delle VM su NAS-01.'],
        ];
        foreach ($vms as $n => [$name, $os, $vcpu, $ram, $disk, $ip, $desc]) {
            $vm = $this->equipment(EquipmentType::VirtualMachine, $name, null, null, [
                'host_equipment_id' => $pve->getKey(),
                'room_id' => $ced->getKey(),
                'position_x' => 1.0 + $n * 0.6,
                'position_y' => 2.4,
                'management_ip' => $ip,
                'custom_fields' => ['vcpu' => $vcpu, 'ram_mb' => $ram, 'disk_gb' => $disk, 'guest_os' => $os],
                'description' => $desc,
            ]);
            $this->iface($vm, 'ens18', [
                'type' => InterfaceType::Virtual,
                'media' => InterfaceMedia::Virtual,
                'vlan_mode' => InterfaceVlanMode::Access,
                'vlan_default' => 20,
                'ip_address' => "{$ip}/24",
                'backed_by_interface_id' => $this->if('PVE-01', 'eno2')->getKey(),
            ]);
        }

        $nas = $this->mounted($rack, EquipmentType::Nas, 'NAS-01', 14, 2, 'Synology', 'RS822+', [
            'serial' => '2240SBRN81234',
            'firmware' => 'DSM 7.2.1',
            'management_ip' => '10.0.20.20',
            'description' => '4× HDD 8 TB in SHR-1. Destinazione dei backup e replica su cloud.',
        ]);
        $this->iface($nas, 'LAN1', ['vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 20, 'ip_address' => '10.0.20.20/24']);
        $this->iface($nas, 'LAN2', ['status' => InterfaceStatus::Down]);

        $ups = $this->mounted($rack, EquipmentType::Ups, 'UPS-01', 1, 2, 'APC', 'Smart-UPS SMT1500RMI2UNC', [
            'serial' => 'AS2215123456',
            'management_ip' => '10.0.99.20',
            'description' => 'Autonomia ~25 min a carico attuale. Batterie sostituite a marzo 2026.',
        ]);
        $this->iface($ups, 'NMC', ['type' => InterfaceType::Management, 'vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 99, 'ip_address' => '10.0.99.20/24', 'description' => 'Network Management Card']);
    }

    // ── Ufficio ───────────────────────────────────────────────────────────

    private function office(Room $office): void
    {
        $at = fn (float $x, float $y): array => ['room_id' => $office->getKey(), 'position_x' => $x, 'position_y' => $y];

        // Wall outlets along the walls, one keystone each.
        $outlets = ['A01' => [1.0, 0.4], 'A02' => [3.5, 0.4], 'A03' => [6.0, 0.4], 'A04' => [9.6, 2.5], 'A05' => [9.6, 6.0], 'A06' => [5.0, 4.0]];
        foreach ($outlets as $name => [$x, $y]) {
            $outlet = $this->equipment(EquipmentType::WallOutlet, "PRESA-{$name}", 'BTicino', 'Living Now RJ45 Cat6', $at($x, $y));
            $this->keystonePair($outlet, $name, 1);
        }

        foreach ([1 => 1.0, 2 => 3.5, 3 => 6.0] as $n => $x) {
            $pc = $this->equipment(EquipmentType::Computer, "PC-0{$n}", 'HP', 'EliteDesk 800 G9 Mini', $at($x, 1.3) + [
                'serial' => "CZC3{$n}21XYZ",
                'asset_tag' => "RP-PC-00{$n}",
                'management_ip' => "10.0.10.10{$n}",
                'description' => ['Postazione avv. Rossetti', 'Postazione segreteria', 'Postazione praticante'][$n - 1],
            ]);
            $this->iface($pc, 'eth0', ['vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 10, 'ip_address' => "10.0.10.10{$n}/24"]);
        }

        $nb = $this->equipment(EquipmentType::Notebook, 'NB-01', 'Lenovo', 'ThinkPad T14 Gen 5', $at(7.5, 4.0) + [
            'asset_tag' => 'RP-NB-001',
            'management_ip' => '10.0.10.110',
            'description' => 'Notebook del socio, usato in sede (Wi‑Fi) e da remoto (VPN).',
        ]);
        $this->iface($nb, 'wlan0', ['type' => InterfaceType::Wireless, 'media' => InterfaceMedia::Wireless, 'speed_mbps' => 2400, 'vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 10, 'ip_address' => '10.0.10.110/24']);

        $ap = $this->equipment(EquipmentType::AccessPoint, 'AP-01', 'Ubiquiti', 'U6 Pro', $at(5.0, 4.6) + [
            'firmware' => '6.6.77',
            'management_ip' => '10.0.99.30',
            'description' => 'A soffitto, centro open space. Alimentato PoE da SW-CORE.',
        ]);
        $this->iface($ap, 'eth0', ['vlan_mode' => InterfaceVlanMode::Trunk, 'vlan_default' => 99, 'vlans_allowed' => [10, 30, 99], 'poe' => InterfacePoe::Pd, 'ip_address' => '10.0.99.30/24']);
        $this->iface($ap, 'wlan0', ['type' => InterfaceType::Wireless, 'media' => InterfaceMedia::Wireless, 'speed_mbps' => 4800, 'vlan_mode' => InterfaceVlanMode::Trunk, 'vlans_allowed' => [10, 30], 'description' => '2,4 + 5 GHz']);

        $mfp = $this->equipment(EquipmentType::Printer, 'MFP-01', 'Kyocera', 'ECOSYS M8130cidn', $at(9.0, 2.5) + [
            'serial' => 'VVB3X12345',
            'management_ip' => '10.0.10.50',
            'description' => 'Multifunzione A3 a colori, scansione su cartella FILESRV-01.',
        ]);
        $this->iface($mfp, 'eth0', ['vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 10, 'ip_address' => '10.0.10.50/24']);

        $tv = $this->equipment(EquipmentType::Tv, 'TV-SALA', 'Samsung', 'QM55C', $at(9.0, 6.0) + [
            'management_ip' => '10.0.10.60',
            'description' => 'Display della sala riunioni per videoconferenze.',
        ]);
        $this->iface($tv, 'eth0', ['vlan_mode' => InterfaceVlanMode::Access, 'vlan_default' => 10, 'ip_address' => '10.0.10.60/24']);
    }

    // ── Cablaggio ─────────────────────────────────────────────────────────

    private function cabling(): void
    {
        $this->wire('RTR-INTERNET:LAN1', 'FW-01:wan1', 'utp_cat6', 0.5, '#2563eb');
        $this->wire('FW-01:internal1', 'SW-CORE:1', 'utp_cat6a', 0.5, '#dc2626');
        $this->wire('PVE-01:eno1', 'SW-CORE:2', 'utp_cat6a', 1.0, '#6b7280');
        $this->wire('PVE-01:eno2', 'SW-CORE:3', 'utp_cat6a', 1.0, '#16a34a');
        $this->wire('NAS-01:LAN1', 'SW-CORE:4', 'utp_cat6a', 1.0, '#16a34a');
        $this->wire('UPS-01:NMC', 'SW-CORE:5', 'utp_cat6', 1.5, '#6b7280');

        // Switch → patch panel (patch cord) → wall outlet (permanent link) → device.
        $drops = ['A01' => 'PC-01:eth0', 'A02' => 'PC-02:eth0', 'A03' => 'PC-03:eth0', 'A04' => 'MFP-01:eth0', 'A05' => 'TV-SALA:eth0', 'A06' => 'AP-01:eth0'];
        $n = 0;
        foreach ($drops as $outlet => $device) {
            $n++;
            $port = sprintf('P%02d', $n);
            $this->wire('SW-CORE:'.(9 + $n), "PP-01:{$port}:front", 'utp_cat6', 0.5, '#facc15', "Patch {$port}");
            $this->wire("PP-01:{$port}:rear", "PRESA-{$outlet}:{$outlet}:rear", 'utp_cat6', 12.0 + $n * 3, null, "Link {$port}–{$outlet}");
            $this->wire("PRESA-{$outlet}:{$outlet}:front", $device, 'utp_cat6', 3.0, '#3b82f6');
        }
    }

    // ── Wi‑Fi, VPN, tag ───────────────────────────────────────────────────

    private function wireless(Site $site): void
    {
        $broadcaster = $this->if('AP-01', 'wlan0');

        $staff = WifiNetwork::query()->create([
            'site_id' => $site->getKey(),
            'ssid' => 'Rossetti-Staff',
            'security_type' => 'WPA2-Enterprise',
            'vlan_id' => 10,
            'notes' => 'Autenticazione 802.1X su DC-01 (NPS).',
        ]);
        $guest = WifiNetwork::query()->create([
            'site_id' => $site->getKey(),
            'ssid' => 'Rossetti-Guest',
            'security_type' => 'WPA2-PSK',
            'vlan_id' => 30,
            'notes' => 'Rete ospiti isolata, solo Internet. Password cambiata ogni mese.',
        ]);
        $staff->broadcasters()->attach($broadcaster->getKey());
        $guest->broadcasters()->attach($broadcaster->getKey());

        $nb = Equipment::query()->where('name', 'NB-01')->firstOrFail();
        $this->wifiClients->execute($staff, $nb, $this->if('NB-01', 'wlan0'), $broadcaster);
    }

    private function vpn(): void
    {
        $vpn = VpnRemoteAccess::query()->create([
            'name' => 'VPN Studio',
            'protocol' => VpnProtocol::WireGuard,
            'firewall_interface_id' => $this->if('FW-01', 'wan1')->getKey(),
            'routing_mode' => VpnRoutingMode::Routed,
            'client_network_cidr' => '10.0.200.0/24',
            'routed_vlans' => [10, 20],
            'notes' => 'Accesso remoto per soci e collaboratori.',
        ]);

        $nb = Equipment::query()->where('name', 'NB-01')->firstOrFail();
        $this->vpnClients->execute($vpn, $nb, null, 'm.rossetti');
    }

    private function tags(): void
    {
        $critical = Tag::query()->create(['name' => 'critico', 'color' => '#dc2626']);
        $replace = Tag::query()->create(['name' => 'da sostituire', 'color' => '#f59e0b']);
        $warranty = Tag::query()->create(['name' => 'in garanzia', 'color' => '#16a34a']);

        foreach (['FW-01', 'SW-CORE', 'PVE-01', 'DC-01'] as $name) {
            Equipment::query()->where('name', $name)->firstOrFail()->tags()->attach($critical);
        }
        Equipment::query()->where('name', 'PC-03')->firstOrFail()->tags()->attach($replace);
        foreach (['PVE-01', 'NAS-01', 'NB-01'] as $name) {
            Equipment::query()->where('name', $name)->firstOrFail()->tags()->attach($warranty);
        }
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $extra
     */
    private function equipment(EquipmentType $type, string $name, ?string $vendor, ?string $model, array $extra = []): Equipment
    {
        return Equipment::query()->create($extra + [
            'name' => $name,
            'type' => $type,
            'vendor' => $vendor,
            'model' => $model,
            'mounted' => false,
            'status' => EquipmentStatus::Active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function mounted(Rack $rack, EquipmentType $type, string $name, int $startU, int $height, string $vendor, string $model, array $extra = []): Equipment
    {
        return $this->equipment($type, $name, $vendor, $model, [
            'rack_id' => $rack->getKey(),
            'room_id' => $rack->room_id,
            'mounted' => true,
            'position_u_start' => $startU,
            'position_u_height' => $height,
            'position_orient' => 'front',
        ] + $extra);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function iface(Equipment $equipment, string $name, array $attributes = []): NetworkInterface
    {
        $iface = NetworkInterface::query()->create($attributes + [
            'equipment_id' => $equipment->getKey(),
            'name' => $name,
            'type' => InterfaceType::Ethernet,
            'media' => InterfaceMedia::Copper,
            'connector' => ($attributes['media'] ?? InterfaceMedia::Copper) === InterfaceMedia::Copper ? 'RJ45' : null,
            'speed_mbps' => 1000,
            'vlan_mode' => InterfaceVlanMode::None,
            'status' => InterfaceStatus::Up,
            'poe' => InterfacePoe::None,
        ]);

        return $this->ifaces["{$equipment->name}:{$name}"] = $iface;
    }

    private function keystonePair(Equipment $equipment, string $name, int $index): void
    {
        [$front, $rear] = $this->keystones->execute($equipment, ['name' => $name, 'index' => $index, 'connector' => 'RJ45']);
        $this->ifaces["{$equipment->name}:{$name}:front"] = $front;
        $this->ifaces["{$equipment->name}:{$name}:rear"] = $rear;
    }

    private function if(string $equipment, string $name): NetworkInterface
    {
        return $this->ifaces["{$equipment}:{$name}"] ?? throw new RuntimeException("Unknown interface {$equipment}:{$name}");
    }

    private function wire(string $a, string $b, string $cable, ?float $lengthM = null, ?string $color = null, ?string $label = null): void
    {
        [$eqA, $ifA] = explode(':', $a, 2);
        [$eqB, $ifB] = explode(':', $b, 2);

        $this->connections->connect($this->if($eqA, $ifA), $this->if($eqB, $ifB), [
            'cable_type' => $cable,
            'cable_length_m' => $lengthM,
            'cable_label' => $label ?? "{$eqA} ↔ {$eqB}",
            'color' => $color,
        ]);
    }

    /**
     * Floor plan: a single rectangular wall loop. Doors and windows sit at
     * fractions t ∈ [0,1] along the loop's perimeter.
     *
     * @param  list<float>  $doors
     * @param  list<float>  $windows
     * @return array<string, mixed>
     */
    private function rectangle(float $w, float $d, array $doors, array $windows): array
    {
        $wall = 'w-'.substr(md5("{$w}x{$d}"), 0, 8);

        return [
            'version' => 1,
            'walls' => [[
                'id' => $wall,
                'points' => [[0.1, 0.1], [$w - 0.1, 0.1], [$w - 0.1, $d - 0.1], [0.1, $d - 0.1], [0.1, 0.1]],
                'thickness_m' => 0.15,
            ]],
            'doors' => array_map(fn (float $t, int $i) => ['id' => "d-{$wall}-{$i}", 'wall_id' => $wall, 't' => $t, 'width_m' => 0.9, 'swing' => 'left_in'], $doors, array_keys($doors)),
            'windows' => array_map(fn (float $t, int $i) => ['id' => "win-{$wall}-{$i}", 'wall_id' => $wall, 't' => $t, 'width_m' => 1.2], $windows, array_keys($windows)),
            'labels' => [],
        ];
    }
}
