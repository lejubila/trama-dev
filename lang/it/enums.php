<?php

declare(strict_types=1);

// Enum labels that differ by language; technical terms (Switch, NAS…) stay literal in the enums.
return [
    'equipment_type' => [
        'wall_outlet' => 'Presa a muro',
        'virtual_machine' => 'Macchina virtuale',
        'phone_system' => 'Centralino telefonico',
        'intercom' => 'Citofono',
        'access_control' => 'Controllo accessi',
        'camera' => 'Telecamera',
        'printer' => 'Stampante',
        'iot_device' => 'Dispositivo IoT',
        'other' => 'Altro',
    ],
    'equipment_category' => [
        'network' => 'Rete',
        'passive_cabling' => 'Cablaggio passivo',
        'server_storage' => 'Server & storage',
        'power' => 'Alimentazione',
        'management' => 'Gestione',
        'voice_comms' => 'Telefonia & comunicazione',
        'security' => 'Sicurezza & videosorveglianza',
        'end_user' => 'Postazioni & end-user',
        'other' => 'Altro',
    ],
    'equipment_status' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
        'maintenance' => 'In manutenzione',
        'decommissioned' => 'Dismesso',
    ],
    'interface_side' => [
        'front' => 'Anteriore',
        'rear' => 'Posteriore',
    ],
    'user_role' => [
        'admin' => 'Amministratore',
        'tecnico' => 'Tecnico',
        'cliente' => 'Cliente',
    ],
    'vpn_protocol' => [
        'other' => 'Altro',
    ],
    'hypervisor_vendor' => [
        'generic' => 'Generico',
    ],
];
