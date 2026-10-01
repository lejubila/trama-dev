<?php

declare(strict_types=1);

use App\Services\TenantTransfer\ViewStateRemapper;

$maps = [
    'sites' => [1 => 101],
    'rooms' => [2 => 102],
    'racks' => [3 => 103],
    'equipment' => [4 => 104, 5 => 105],
    'interfaces' => [6 => 106],
    'tags' => [7 => 107],
    'wifi_networks' => [8 => 108],
    'vpn_remote_access' => [9 => 109],
    'vpn_site_to_site' => [10 => 110],
    'topology_snapshots' => [11 => 111],
];

it('remaps ids and node keys in a topology view state', function () use ($maps): void {
    $state = (new ViewStateRemapper($maps))->viewState([
        'siteId' => 1,
        'roomFilter' => 99, // vanished → reset to "all"
        'vlanFilter' => 20, // a VLAN number, not an id: untouched
        'tagFilters' => ['7', '99'],
        'sessionHiddenIds' => [5],
        'portSettings' => ['6' => ['ip' => true], '99' => ['ip' => false]],
        'nodePositions' => [
            'eq-4' => [0, 0], 'host-5' => [1, 1], 'rack-3' => [2, 2], 'room-2' => [3, 3],
            'site-1' => [4, 4], 'wifi-8' => [5, 5], 'vpn-ra-9' => [6, 6], 'vpn-stos-10' => [7, 7],
            'eq-99' => [8, 8], 'custom' => [9, 9],
        ],
        'vpnNodeDetails' => ['vpn-ra-9' => true],
    ]);

    expect($state['siteId'])->toBe(101)
        ->and($state['roomFilter'])->toBe(0)
        ->and($state['vlanFilter'])->toBe(20)
        ->and($state['tagFilters'])->toBe(['107'])
        ->and($state['sessionHiddenIds'])->toBe([105])
        ->and(array_keys($state['portSettings']))->toBe([106])
        ->and(array_keys($state['nodePositions']))->toBe([
            'eq-104', 'host-105', 'rack-103', 'room-102', 'site-101', 'wifi-108', 'vpn-ra-109', 'vpn-stos-110', 'custom',
        ])
        ->and(array_keys($state['vpnNodeDetails']))->toBe(['vpn-ra-109']);
});

it('remaps ids in document parameters', function () use ($maps): void {
    $params = (new ViewStateRemapper($maps))->documentParameters([
        'sections' => [
            'sites' => ['enabled' => true, 'ids' => [1]],
            'rooms' => ['enabled' => true, 'ids' => [2, 99]],
            'racks' => ['enabled' => false, 'ids' => [3]],
            'equipment' => ['enabled' => true, 'ids' => [4, 5], 'ports_excluded' => [6]],
            'topologies' => ['enabled' => true, 'items' => [['id' => 11, 'orientation' => 'portrait'], ['id' => 99]]],
        ],
        'options' => ['include_cover' => true],
    ]);

    expect($params['sections']['sites']['ids'])->toBe([101])
        ->and($params['sections']['rooms']['ids'])->toBe([102])
        ->and($params['sections']['racks']['ids'])->toBe([103])
        ->and($params['sections']['equipment']['ids'])->toBe([104, 105])
        ->and($params['sections']['equipment']['ports_excluded'])->toBe([106])
        ->and($params['sections']['topologies']['items'])->toBe([['id' => 111, 'orientation' => 'portrait']])
        ->and($params['options'])->toBe(['include_cover' => true]);
});
