<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EquipmentType;
use App\Enums\InterfaceMedia;
use App\Enums\InterfacePoe;
use App\Enums\InterfaceStatus;
use App\Enums\InterfaceType;
use App\Enums\InterfaceVlanMode;
use App\Models\Connection;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\ConnectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Builds a realistic-but-small dataset:
 * - ACME Spa: 2 sites (Milano, Roma), 3 rooms, 4 racks, ~20 equipment,
 *   ~120 interfaces, ~14 active connections forming a hierarchical topology
 * - Beta Srl: 1 site, 1 rack, 5 equipment
 *
 * Use migrate:fresh --seed to repopulate; the unique() faker calls will
 * collide on a re-run.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function run(): void
    {
        $acme = Tenant::query()->where('slug', 'acme')->firstOrFail();
        $beta = Tenant::query()->where('slug', 'beta')->firstOrFail();

        $this->seedAcme($acme);
        $this->seedBeta($beta);

        TenantContext::clear();
    }

    private function seedAcme(Tenant $tenant): void
    {
        TenantContext::setId($tenant->getKey());

        $milano = Site::factory()->create(['name' => 'Sede Milano', 'address' => 'Via Solferino 28, Milano']);
        $roma = Site::factory()->create(['name' => 'Sede Roma', 'address' => 'Via Veneto 10, Roma']);

        $cedMilano = Room::factory()->create(['site_id' => $milano->id, 'name' => 'CED Milano', 'floor' => 'Piano -1']);
        $cedRoma = Room::factory()->create(['site_id' => $roma->id, 'name' => 'CED Roma', 'floor' => 'Piano 0']);
        $edgeRoma = Room::factory()->create(['site_id' => $roma->id, 'name' => 'Edge Roma', 'floor' => 'Piano 2']);

        $rackMilano = Rack::factory()->create(['room_id' => $cedMilano->id, 'name' => 'Rack-MI-A1', 'height_units' => 42]);
        $rackRoma1 = Rack::factory()->create(['room_id' => $cedRoma->id, 'name' => 'Rack-RM-A1', 'height_units' => 42]);
        $rackRoma2 = Rack::factory()->create(['room_id' => $cedRoma->id, 'name' => 'Rack-RM-A2', 'height_units' => 42]);
        $rackEdgeRoma = Rack::factory()->create(['room_id' => $edgeRoma->id, 'name' => 'Rack-RM-EDGE', 'height_units' => 12]);

        // === MILANO ===
        $fwMilano = $this->mountedEquipment(EquipmentType::Firewall, 'FW-MI', $rackMilano, 40, 1, 'Fortinet', 'FortiGate-200F');
        $rtrMilano = $this->mountedEquipment(EquipmentType::Router, 'RTR-MI', $rackMilano, 38, 1, 'Cisco', 'ISR4321');
        $coreSw1Milano = $this->mountedEquipment(EquipmentType::Switch, 'CORE-SW1-MI', $rackMilano, 36, 1, 'Cisco', 'Catalyst 9300-48');
        $coreSw2Milano = $this->mountedEquipment(EquipmentType::Switch, 'CORE-SW2-MI', $rackMilano, 35, 1, 'Cisco', 'Catalyst 9300-48');
        $accSw1Milano = $this->mountedEquipment(EquipmentType::Switch, 'ACC-SW1-MI', $rackMilano, 33, 1, 'HPE Aruba', 'CX 6300M');
        $accSw2Milano = $this->mountedEquipment(EquipmentType::Switch, 'ACC-SW2-MI', $rackMilano, 32, 1, 'HPE Aruba', 'CX 6300M');
        $wlcMilano = $this->mountedEquipment(EquipmentType::Controller, 'WLC-MI', $rackMilano, 30, 1, 'Cisco', '9800-CL');
        $apMi1 = $this->equipment(EquipmentType::AccessPoint, 'AP-MI-01', 'Cisco', 'C9120AXI');
        $apMi2 = $this->equipment(EquipmentType::AccessPoint, 'AP-MI-02', 'Cisco', 'C9120AXI');
        $ppMilano = $this->mountedEquipment(EquipmentType::PatchPanel, 'PP-MI-01', $rackMilano, 41, 1, 'Panduit', 'CPP24WS');

        // === ROMA CED ===
        $rtrRoma = $this->mountedEquipment(EquipmentType::Router, 'RTR-RM', $rackRoma1, 40, 1, 'Cisco', 'ISR4321');
        $coreSwRoma = $this->mountedEquipment(EquipmentType::Switch, 'CORE-SW-RM', $rackRoma1, 38, 1, 'Cisco', 'Catalyst 9300-24');
        $accSwRoma = $this->mountedEquipment(EquipmentType::Switch, 'ACC-SW1-RM', $rackRoma2, 35, 1, 'MikroTik', 'CRS328');
        $ppRoma = $this->mountedEquipment(EquipmentType::PatchPanel, 'PP-RM-01', $rackRoma1, 41, 1, 'Panduit', 'CPP24WS');

        // === ROMA EDGE ===
        $edgeSwRoma = $this->mountedEquipment(EquipmentType::Switch, 'EDGE-SW-RM', $rackEdgeRoma, 11, 1, 'Ubiquiti', 'USW-Pro-24');
        $apRm1 = $this->equipment(EquipmentType::AccessPoint, 'AP-RM-01', 'Ubiquiti', 'U6-Pro');
        $apRm2 = $this->equipment(EquipmentType::AccessPoint, 'AP-RM-02', 'Ubiquiti', 'U6-Pro');

        // Interfaces
        $this->ethernetUplink($fwMilano, 'WAN1', InterfaceVlanMode::None);
        $this->ethernetUplink($fwMilano, 'LAN1', InterfaceVlanMode::Trunk);
        $this->ethernetUplink($rtrMilano, 'Gi0/0', InterfaceVlanMode::Trunk);
        $this->ethernetUplink($rtrMilano, 'Gi0/1', InterfaceVlanMode::None);
        $this->switchPorts($coreSw1Milano, 24);
        $this->switchPorts($coreSw2Milano, 24);
        $this->switchPorts($accSw1Milano, 24);
        $this->switchPorts($accSw2Milano, 24);
        $this->ethernetUplink($wlcMilano, 'Gi1', InterfaceVlanMode::Trunk);
        $this->wirelessUplink($apMi1);
        $this->wirelessUplink($apMi2);
        $this->ethernetUplink($ppMilano, 'P-01', InterfaceVlanMode::None);

        $this->ethernetUplink($rtrRoma, 'Gi0/0', InterfaceVlanMode::Trunk);
        $this->ethernetUplink($rtrRoma, 'Gi0/1', InterfaceVlanMode::None);
        $this->switchPorts($coreSwRoma, 24);
        $this->switchPorts($accSwRoma, 24);
        $this->ethernetUplink($ppRoma, 'P-01', InterfaceVlanMode::None);

        $this->switchPorts($edgeSwRoma, 24);
        $this->wirelessUplink($apRm1);
        $this->wirelessUplink($apRm2);

        // Connections
        $this->wire($fwMilano, 'LAN1', $rtrMilano, 'Gi0/0', 'utp_cat6a');
        $this->wire($rtrMilano, 'Gi0/1', $coreSw1Milano, 'Gi1/0/1', 'fiber_om4');
        $this->wire($coreSw1Milano, 'Gi1/0/24', $coreSw2Milano, 'Gi1/0/24', 'fiber_om4');
        $this->wire($coreSw1Milano, 'Gi1/0/2', $accSw1Milano, 'Gi1/0/1', 'fiber_om4');
        $this->wire($coreSw1Milano, 'Gi1/0/3', $accSw2Milano, 'Gi1/0/1', 'fiber_om4');
        $this->wire($wlcMilano, 'Gi1', $coreSw1Milano, 'Gi1/0/4', 'utp_cat6a');
        $this->wire($apMi1, 'wlan0', $accSw1Milano, 'Gi1/0/2', 'utp_cat6');
        $this->wire($apMi2, 'wlan0', $accSw1Milano, 'Gi1/0/3', 'utp_cat6');

        $this->wire($rtrRoma, 'Gi0/1', $coreSwRoma, 'Gi1/0/1', 'fiber_om4');
        $this->wire($coreSwRoma, 'Gi1/0/2', $accSwRoma, 'ether1', 'fiber_om4');
        $this->wire($coreSwRoma, 'Gi1/0/3', $edgeSwRoma, 'port1', 'fiber_om4');
        $this->wire($apRm1, 'wlan0', $edgeSwRoma, 'port2', 'utp_cat6');
        $this->wire($apRm2, 'wlan0', $edgeSwRoma, 'port3', 'utp_cat6');
    }

    private function seedBeta(Tenant $tenant): void
    {
        TenantContext::setId($tenant->getKey());

        $sede = Site::factory()->create(['name' => 'Sede Beta', 'address' => 'Via Marconi 5, Bologna']);
        $room = Room::factory()->create(['site_id' => $sede->id, 'name' => 'Server Room', 'floor' => 'Piano 1']);
        $rack = Rack::factory()->create(['room_id' => $room->id, 'name' => 'Rack-Beta-01', 'height_units' => 24]);

        $fw = $this->mountedEquipment(EquipmentType::Firewall, 'FW-BT', $rack, 23, 1, 'MikroTik', 'CCR2004');
        $sw = $this->mountedEquipment(EquipmentType::Switch, 'SW-BT', $rack, 22, 1, 'MikroTik', 'CRS328');
        $srv = $this->mountedEquipment(EquipmentType::Server, 'SRV-BT-01', $rack, 18, 2, 'Dell', 'PowerEdge R640');
        $this->mountedEquipment(EquipmentType::Ups, 'UPS-BT', $rack, 1, 2, 'APC', 'Smart-UPS 1500VA');
        $ap = $this->equipment(EquipmentType::AccessPoint, 'AP-BT-01', 'MikroTik', 'cAP ax');

        $this->ethernetUplink($fw, 'WAN', InterfaceVlanMode::None);
        $this->ethernetUplink($fw, 'LAN', InterfaceVlanMode::Trunk);
        $this->switchPorts($sw, 16);
        $this->ethernetUplink($srv, 'eno1', InterfaceVlanMode::Access);
        $this->wirelessUplink($ap);

        $this->wire($fw, 'LAN', $sw, 'ether1', 'utp_cat6a');
        $this->wire($srv, 'eno1', $sw, 'ether2', 'utp_cat6');
        $this->wire($ap, 'wlan0', $sw, 'ether3', 'utp_cat6');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function equipment(EquipmentType $type, string $name, string $vendor, string $model): Equipment
    {
        return Equipment::factory()->ofType($type)->create([
            'name' => $name,
            'vendor' => $vendor,
            'model' => $model,
        ]);
    }

    private function mountedEquipment(
        EquipmentType $type,
        string $name,
        Rack $rack,
        int $startU,
        int $heightU,
        string $vendor,
        string $model,
    ): Equipment {
        return Equipment::factory()->ofType($type)->mountedAt($startU, $heightU)->create([
            'name' => $name,
            'rack_id' => $rack->getKey(),
            'vendor' => $vendor,
            'model' => $model,
        ]);
    }

    private function ethernetUplink(Equipment $eq, string $name, InterfaceVlanMode $mode): NetworkInterface
    {
        return NetworkInterface::factory()->ethernet()->create([
            'equipment_id' => $eq->getKey(),
            'name' => $name,
            'vlan_mode' => $mode,
            'vlan_default' => $mode === InterfaceVlanMode::None ? null : 1,
            'speed_mbps' => 1000,
        ]);
    }

    private function switchPorts(Equipment $sw, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            NetworkInterface::factory()->ethernet()->create([
                'equipment_id' => $sw->getKey(),
                'name' => $this->portNameFor($sw, $i),
                'index' => $i,
                'vlan_mode' => $i === 1 || $i === $count ? InterfaceVlanMode::Trunk : InterfaceVlanMode::Access,
                'vlan_default' => 1,
                'poe' => in_array($sw->vendor, ['HPE Aruba', 'Ubiquiti'], true) ? InterfacePoe::Pse : InterfacePoe::None,
            ]);
        }
    }

    private function wirelessUplink(Equipment $ap): NetworkInterface
    {
        return NetworkInterface::factory()->wireless()->create([
            'equipment_id' => $ap->getKey(),
            'name' => 'wlan0',
            'type' => InterfaceType::Wireless,
            'media' => InterfaceMedia::Wireless,
            'poe' => InterfacePoe::Pd,
            'status' => InterfaceStatus::Up,
        ]);
    }

    private function portNameFor(Equipment $sw, int $i): string
    {
        return match ($sw->vendor) {
            'MikroTik' => 'ether'.$i,
            'Ubiquiti' => 'port'.$i,
            default => 'Gi1/0/'.$i,
        };
    }

    private function wire(Equipment $a, string $aIfName, Equipment $b, string $bIfName, string $cableType): Connection
    {
        $aIf = NetworkInterface::query()->where('equipment_id', $a->getKey())->where('name', $aIfName)->firstOrFail();
        $bIf = NetworkInterface::query()->where('equipment_id', $b->getKey())->where('name', $bIfName)->firstOrFail();

        return $this->connections->connect($aIf, $bIf, [
            'cable_type' => $cableType,
            'cable_label' => "{$a->name}:{$aIfName} ↔ {$b->name}:{$bIfName}",
        ]);
    }
}
