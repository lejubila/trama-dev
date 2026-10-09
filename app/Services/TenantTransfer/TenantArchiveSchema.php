<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

use App\Models\Connection;
use App\Models\Equipment;

/**
 * Single source of truth for what a tenant archive contains, shared by the
 * exporter and the importer so the two can never drift apart.
 *
 * Sections are listed in dependency order: every `refs` target appears
 * before the section that points to it. Self-references (`deferred`) are
 * resolved in a second pass once every row of the section has a new id.
 */
final class TenantArchiveSchema
{
    public const FORMAT = 'trama-tenant-export';

    public const VERSION = 1;

    /**
     * Never copied verbatim: ids are remapped, tenant is the new one, users
     * don't travel between installations and soft-deleted rows aren't exported.
     */
    public const SKIPPED_COLUMNS = ['id', 'tenant_id', 'deleted_at', 'created_by'];

    /**
     * Polymorphic taggables are stored by section name, never by class name,
     * so an archive can't make the importer write arbitrary morph types.
     *
     * @var array<string, class-string>
     */
    public const TAGGABLE_TYPES = [
        'equipment' => Equipment::class,
        'connections' => Connection::class,
    ];

    /**
     * @return array<string, array{
     *   table: string,
     *   refs?: array<string, array{0: string, 1: bool}>,
     *   deferred?: array<string, string>,
     *   files?: list<string>,
     *   json?: list<string>,
     *   soft_deletes?: bool,
     *   created_by?: bool,
     * }>
     *
     * `refs`: column => [target section, required]. A missing required
     * reference drops the row on export and aborts the import; a missing
     * optional one becomes NULL.
     */
    public static function sections(): array
    {
        return [
            'tags' => ['table' => 'tags'],
            'sites' => ['table' => 'sites', 'soft_deletes' => true],
            'rooms' => [
                'table' => 'rooms',
                'refs' => ['site_id' => ['sites', true]],
                'files' => ['floor_plan_path'],
                'json' => ['floor_plan_drawing'],
                'soft_deletes' => true,
            ],
            'racks' => [
                'table' => 'racks',
                'refs' => ['room_id' => ['rooms', true]],
                'files' => ['icon_path'],
                'soft_deletes' => true,
            ],
            'rack_photos' => [
                'table' => 'rack_photos',
                'refs' => ['rack_id' => ['racks', true]],
                'files' => ['photo_path'],
                'created_by' => true,
            ],
            'equipment' => [
                'table' => 'equipment',
                'refs' => ['rack_id' => ['racks', false], 'room_id' => ['rooms', false]],
                'deferred' => ['host_equipment_id' => 'equipment'],
                'files' => ['icon_path'],
                'json' => ['custom_fields'],
                'soft_deletes' => true,
            ],
            'interfaces' => [
                'table' => 'interfaces',
                'refs' => ['equipment_id' => ['equipment', true]],
                'deferred' => ['paired_interface_id' => 'interfaces', 'backed_by_interface_id' => 'interfaces'],
                'json' => ['vlans_allowed', 'custom_fields'],
            ],
            'connections' => [
                'table' => 'connections',
                'refs' => ['from_interface_id' => ['interfaces', true], 'to_interface_id' => ['interfaces', true]],
            ],
            'link_groups' => ['table' => 'link_groups'],
            'wifi_networks' => [
                'table' => 'wifi_networks',
                'refs' => ['site_id' => ['sites', false]],
                'soft_deletes' => true,
            ],
            'wifi_associations' => [
                'table' => 'wifi_associations',
                'refs' => [
                    'wifi_network_id' => ['wifi_networks', true],
                    'client_interface_id' => ['interfaces', true],
                    'preferred_broadcaster_interface_id' => ['interfaces', false],
                ],
            ],
            'vpn_remote_access' => [
                'table' => 'vpn_remote_access',
                'refs' => ['firewall_interface_id' => ['interfaces', true]],
                'json' => ['routed_vlans'],
                'soft_deletes' => true,
            ],
            'vpn_remote_access_clients' => [
                'table' => 'vpn_remote_access_clients',
                'refs' => [
                    'vpn_remote_access_id' => ['vpn_remote_access', true],
                    'client_interface_id' => ['interfaces', true],
                ],
            ],
            'vpn_site_to_site' => [
                'table' => 'vpn_site_to_site',
                'refs' => [
                    'endpoint_a_interface_id' => ['interfaces', true],
                    'endpoint_b_interface_id' => ['interfaces', true],
                ],
                'json' => ['routed_vlans_a', 'routed_vlans_b', 'routed_networks_a', 'routed_networks_b'],
                'soft_deletes' => true,
            ],
            'device_icons' => ['table' => 'device_icons', 'files' => ['image_path']],
            'topology_snapshots' => [
                'table' => 'topology_snapshots',
                'files' => ['image_path'],
                'json' => ['view_state'],
                'created_by' => true,
            ],
            'documents' => [
                'table' => 'documents',
                'files' => ['pdf_path'],
                'json' => ['parameters'],
                'created_by' => true,
            ],
        ];
    }

    /**
     * Human labels for the import preview, in display order.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $sections = [
            'sites', 'rooms', 'racks', 'rack_photos', 'equipment', 'interfaces', 'connections',
            'link_groups', 'tags', 'wifi_networks', 'wifi_associations', 'vpn_remote_access',
            'vpn_remote_access_clients', 'vpn_site_to_site', 'device_icons', 'topology_snapshots', 'documents',
        ];

        return array_combine($sections, array_map(fn (string $s) => __('tenants.section_'.$s), $sections));
    }
}
