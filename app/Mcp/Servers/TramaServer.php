<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddInterface;
use App\Mcp\Tools\AttachVpnClient;
use App\Mcp\Tools\AttachWifiClient;
use App\Mcp\Tools\ConnectInterfaces;
use App\Mcp\Tools\CreateEquipment;
use App\Mcp\Tools\CreateRack;
use App\Mcp\Tools\CreateRoom;
use App\Mcp\Tools\CreateSite;
use App\Mcp\Tools\CreateVpnRemoteAccess;
use App\Mcp\Tools\CreateVpnSiteToSite;
use App\Mcp\Tools\CreateWifiNetwork;
use App\Mcp\Tools\DeleteEquipment;
use App\Mcp\Tools\DeleteInterface;
use App\Mcp\Tools\DeleteVpn;
use App\Mcp\Tools\DeleteWifiNetwork;
use App\Mcp\Tools\DetachVpnClient;
use App\Mcp\Tools\DetachWifiClient;
use App\Mcp\Tools\DisconnectInterfaces;
use App\Mcp\Tools\GetEquipment;
use App\Mcp\Tools\GetRack;
use App\Mcp\Tools\GetTopology;
use App\Mcp\Tools\GetVpn;
use App\Mcp\Tools\GetWifiNetwork;
use App\Mcp\Tools\ListConnections;
use App\Mcp\Tools\ListEquipment;
use App\Mcp\Tools\ListRacks;
use App\Mcp\Tools\ListRooms;
use App\Mcp\Tools\ListSites;
use App\Mcp\Tools\ListTenants;
use App\Mcp\Tools\ListVpns;
use App\Mcp\Tools\ListWifiNetworks;
use App\Mcp\Tools\Search;
use App\Mcp\Tools\UpdateEquipment;
use App\Mcp\Tools\UpdateInterface;
use App\Mcp\Tools\UpdateWifiNetwork;
use Laravel\Mcp\Server;

/**
 * MCP server for AI assistants. Every tool goes through the same token
 * abilities, tenant allow-list and policies as the REST API; tools the
 * token cannot use are not even listed.
 */
class TramaServer extends Server
{
    protected string $name = 'Trama Network';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Trama Network documents the network infrastructure of a system integrator's customers.

        Data model, per customer (tenant): site → room → rack → device (equipment) → interface (port).
        A connection is a physical cable between two interfaces of different devices; each interface has
        at most one active cable. Wi-Fi networks (SSIDs) are broadcast by wireless interfaces of access
        points/controllers and have associated client devices. VPNs are site-to-site (between two firewall
        interfaces) or remote access (clients connecting to a firewall interface).

        How to work:
        - Call list_tenants first and pass tenant_id to every other tool (it may be omitted only when the
          token can access a single customer).
        - Use search / list_* / get_* to find ids before changing anything; never guess ids.
        - get_equipment shows every port and what it is cabled to; get_rack shows free rack units.
        - For deletions and disconnections, ask the user to confirm first.
        - A tool may answer with an error explaining a missing permission or invalid data: report it to the
          user instead of retrying blindly.
        MARKDOWN;

    // All tools fit in one tools/list page: some clients don't follow cursors.
    public int $defaultPaginationLength = 50;

    protected array $tools = [
        ListTenants::class,
        Search::class,
        ListSites::class,
        ListRooms::class,
        ListRacks::class,
        GetRack::class,
        ListEquipment::class,
        GetEquipment::class,
        ListConnections::class,
        GetTopology::class,
        ListWifiNetworks::class,
        GetWifiNetwork::class,
        ListVpns::class,
        GetVpn::class,
        CreateSite::class,
        CreateRoom::class,
        CreateRack::class,
        CreateEquipment::class,
        UpdateEquipment::class,
        DeleteEquipment::class,
        AddInterface::class,
        UpdateInterface::class,
        DeleteInterface::class,
        ConnectInterfaces::class,
        DisconnectInterfaces::class,
        CreateWifiNetwork::class,
        UpdateWifiNetwork::class,
        DeleteWifiNetwork::class,
        AttachWifiClient::class,
        DetachWifiClient::class,
        CreateVpnSiteToSite::class,
        CreateVpnRemoteAccess::class,
        AttachVpnClient::class,
        DetachVpnClient::class,
        DeleteVpn::class,
    ];
}
