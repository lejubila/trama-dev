<?php

declare(strict_types=1);

// Enum labels that differ by language; technical terms (Switch, NAS…) stay literal in the enums.
return [
    'equipment_type' => [
        'wall_outlet' => 'Wall outlet',
        'virtual_machine' => 'Virtual machine',
        'phone_system' => 'Phone system (PBX)',
        'intercom' => 'Intercom',
        'access_control' => 'Access control',
        'camera' => 'Camera',
        'printer' => 'Printer',
        'iot_device' => 'IoT device',
        'other' => 'Other',
    ],
    'equipment_category' => [
        'network' => 'Network',
        'passive_cabling' => 'Passive cabling',
        'server_storage' => 'Server & storage',
        'power' => 'Power',
        'management' => 'Management',
        'voice_comms' => 'Voice & communications',
        'security' => 'Security & video surveillance',
        'end_user' => 'Workstations & end-user',
        'other' => 'Other',
    ],
    'equipment_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'maintenance' => 'Under maintenance',
        'decommissioned' => 'Decommissioned',
    ],
    'interface_side' => [
        'front' => 'Front',
        'rear' => 'Rear',
    ],
    'user_role' => [
        'admin' => 'Administrator',
        'tecnico' => 'Technician',
        'cliente' => 'Customer',
    ],
    'vpn_protocol' => [
        'other' => 'Other',
    ],
    'hypervisor_vendor' => [
        'generic' => 'Generic',
    ],
];
