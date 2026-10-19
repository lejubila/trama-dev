<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * Maps the model-friendly tool arguments onto the REST API payloads.
 */
final class Payloads
{
    public const IFACE_KEYS = [
        'name', 'type', 'media', 'status', 'poe', 'speed_mbps', 'connector', 'vlan_mode',
        'vlan_default', 'vlans_allowed', 'ip_address', 'mac_address', 'description',
    ];

    public const WIFI_KEYS = [
        'ssid', 'site_id', 'security_type', 'vlan_id', 'hidden_ssid', 'notes', 'broadcaster_interface_ids',
    ];

    public const EQUIPMENT_KEYS = [
        'name', 'type', 'vendor', 'model', 'serial', 'firmware', 'asset_tag', 'status',
        'management_ip', 'description', 'rack_id',
    ];

    /**
     * u_start / u_height / side become the API's position fields; a rack
     * plus a start unit means the device is mounted.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function equipment(array $args): array
    {
        $data = array_intersect_key($args, array_flip(self::EQUIPMENT_KEYS));

        foreach (['u_start' => 'position_u_start', 'u_height' => 'position_u_height', 'side' => 'position_orient'] as $from => $to) {
            if (array_key_exists($from, $args)) {
                $data[$to] = $args[$from];
            }
        }

        if (isset($args['rack_id'], $args['u_start'])) {
            $data['mounted'] = true;
        }

        return $data;
    }
}
