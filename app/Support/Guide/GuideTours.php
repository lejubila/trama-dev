<?php

declare(strict_types=1);

namespace App\Support\Guide;

use App\Models\Connection;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\NetworkInterface;
use App\Models\Rack;
use App\Models\Room;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\TopologySnapshot;
use App\Models\User;
use App\Models\VpnRemoteAccess;
use App\Models\WifiNetwork;
use App\Support\Tenancy\TenantContext;

/**
 * The guided tours, as data for the JS orchestrator (resources/js/guide).
 * Each step points at a real page and a `data-guide` element of it; the
 * user fills the real forms and the step advances on its own:
 *
 *  - `next`             the popover's "Next" button
 *  - `appear:<target>`  when another `data-guide` element shows up (a modal)
 *  - `created:<entity>` when a Livewire component reports the creation
 *                       (see App\Livewire\Concerns\NotifiesGuide)
 *  - `route:<name>`     when the user opens a page of that route; its
 *                       parameter is remembered for the next steps
 *
 * Route parameters are filled from that memory (`{site}` → last site
 * created or picked), and `skip_if` drops "pick one" steps when the value
 * is already known, e.g. while running the whole path in sequence.
 */
final class GuideTours
{
    /** Session key of the last creation reported by NotifiesGuide. */
    public const LAST_CREATED_KEY = 'guide.last_created';

    /** Tours in the order of the full path. */
    public const ORDER = ['tenant', 'site', 'room', 'rack', 'equipment', 'interfaces', 'connection', 'wifi', 'vpn', 'snapshot', 'document'];

    /** Routes whose parameter a `route:` step can capture. */
    private const PARAM_ROUTES = ['sites.show' => 'site', 'racks.show' => 'rack', 'equipment.show' => 'equipment', 'tenants.manage' => 'tenant'];

    /**
     * @return array<string, array{ability: class-string, minutes: int, steps: list<array<string, mixed>>}>
     */
    private function definitions(): array
    {
        return [
            'tenant' => ['ability' => Tenant::class, 'minutes' => 1, 'steps' => [
                ['key' => 'tenant.open', 'route' => 'tenants.index', 'target' => 'tenants.new', 'advance' => 'appear:tenants.form'],
                ['key' => 'tenant.form', 'route' => 'tenants.index', 'target' => 'tenants.form', 'advance' => 'created:tenant'],
            ]],
            'site' => ['ability' => Site::class, 'minutes' => 1, 'steps' => [
                ['key' => 'site.open', 'route' => 'sites.index', 'target' => 'sites.new', 'advance' => 'appear:sites.form'],
                ['key' => 'site.form', 'route' => 'sites.index', 'target' => 'sites.form', 'advance' => 'created:site'],
            ]],
            'room' => ['ability' => Room::class, 'minutes' => 1, 'steps' => [
                ['key' => 'room.pick', 'route' => 'sites.index', 'target' => 'sites.list', 'advance' => 'route:sites.show', 'skip_if' => 'site'],
                ['key' => 'room.open', 'route' => 'sites.show', 'target' => 'rooms.new', 'advance' => 'appear:rooms.form'],
                ['key' => 'room.form', 'route' => 'sites.show', 'target' => 'rooms.form', 'advance' => 'created:room'],
            ]],
            'rack' => ['ability' => Rack::class, 'minutes' => 1, 'steps' => [
                ['key' => 'rack.open', 'route' => 'racks.index', 'target' => 'racks.new', 'advance' => 'appear:racks.form'],
                ['key' => 'rack.form', 'route' => 'racks.index', 'target' => 'racks.form', 'advance' => 'created:rack'],
            ]],
            'equipment' => ['ability' => Equipment::class, 'minutes' => 2, 'steps' => [
                ['key' => 'equipment.pick', 'route' => 'racks.index', 'target' => 'racks.list', 'advance' => 'route:racks.show', 'skip_if' => 'rack'],
                ['key' => 'equipment.slot', 'route' => 'racks.show', 'target' => 'rack.elevation', 'advance' => 'appear:rack.equipment-form'],
                ['key' => 'equipment.form', 'route' => 'racks.show', 'target' => 'rack.equipment-form', 'advance' => 'created:equipment'],
            ]],
            'interfaces' => ['ability' => NetworkInterface::class, 'minutes' => 2, 'steps' => [
                ['key' => 'interfaces.pick', 'route' => 'equipment.index', 'target' => 'equipment.list', 'advance' => 'route:equipment.show', 'skip_if' => 'equipment'],
                ['key' => 'interfaces.open', 'route' => 'equipment.show', 'query' => ['tab' => 'interfaces'], 'target' => 'interfaces.new', 'advance' => 'appear:interfaces.form'],
                ['key' => 'interfaces.form', 'route' => 'equipment.show', 'query' => ['tab' => 'interfaces'], 'target' => 'interfaces.form', 'advance' => 'created:interface'],
                // Ports usually come in batches: reopening the form loops back.
                ['key' => 'interfaces.more', 'route' => 'equipment.show', 'query' => ['tab' => 'interfaces'], 'target' => 'interfaces.new', 'advance' => 'next', 'loop' => ['appear' => 'interfaces.form', 'to' => 2]],
            ]],
            'connection' => ['ability' => Connection::class, 'minutes' => 2, 'steps' => [
                ['key' => 'connection.wizard', 'route' => 'connections.create', 'ctx_query' => ['from_equipment' => 'equipment'], 'target' => 'connections.wizard', 'advance' => 'created:connection'],
            ]],
            'wifi' => ['ability' => WifiNetwork::class, 'minutes' => 1, 'steps' => [
                ['key' => 'wifi.open', 'route' => 'wifi-networks.index', 'target' => 'wifi.new', 'advance' => 'appear:wifi.form'],
                ['key' => 'wifi.form', 'route' => 'wifi-networks.index', 'target' => 'wifi.form', 'advance' => 'created:wifi'],
            ]],
            'vpn' => ['ability' => VpnRemoteAccess::class, 'minutes' => 1, 'steps' => [
                ['key' => 'vpn.open', 'route' => 'vpns.index', 'target' => 'vpn.new-remote', 'advance' => 'appear:vpn.form'],
                ['key' => 'vpn.form', 'route' => 'vpns.index', 'target' => 'vpn.form', 'advance' => 'created:vpn'],
            ]],
            'snapshot' => ['ability' => TopologySnapshot::class, 'minutes' => 1, 'steps' => [
                ['key' => 'snapshot.canvas', 'route' => 'topology.index', 'target' => 'topology.canvas', 'advance' => 'next'],
                ['key' => 'snapshot.open', 'route' => 'topology.index', 'target' => 'topology.save-snapshot', 'advance' => 'appear:snapshot.form'],
                ['key' => 'snapshot.form', 'route' => 'topology.index', 'target' => 'snapshot.form', 'advance' => 'created:snapshot'],
            ]],
            'document' => ['ability' => Document::class, 'minutes' => 3, 'steps' => [
                ['key' => 'document.info', 'route' => 'documents.create', 'target' => 'documents.info', 'advance' => 'next'],
                ['key' => 'document.sections', 'route' => 'documents.create', 'target' => 'documents.sections', 'advance' => 'next'],
                ['key' => 'document.topologies', 'route' => 'documents.create', 'target' => 'documents.topologies', 'advance' => 'next'],
                ['key' => 'document.wifi', 'route' => 'documents.create', 'target' => 'documents.wifi', 'advance' => 'next'],
                ['key' => 'document.vpn', 'route' => 'documents.create', 'target' => 'documents.vpn', 'advance' => 'next'],
                ['key' => 'document.save', 'route' => 'documents.create', 'target' => 'documents.save', 'advance' => 'created:document'],
            ]],
        ];
    }

    /**
     * The tours this user may follow, ready for the browser: translated,
     * with URL templates instead of route names.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        if (! $user->canManageData()) {
            return [];
        }

        $hasTenant = TenantContext::id() !== null;
        $tours = [];

        foreach ($this->definitions() as $id => $tour) {
            // Everything but the customer itself lives inside a tenant.
            if (($id !== 'tenant' && ! $hasTenant) || ! $user->can('create', $tour['ability'])) {
                continue;
            }

            $tours[$id] = [
                'id' => $id,
                'title' => __("guide.tours.{$id}.title"),
                'description' => __("guide.tours.{$id}.description"),
                'minutes' => $tour['minutes'],
                'steps' => array_map(fn (array $step): array => [
                    'title' => __('guide.steps.'.$this->langKey($step['key']).'.title'),
                    'text' => __('guide.steps.'.$this->langKey($step['key']).'.text'),
                    'url' => $this->template($step['route']),
                    'query' => $step['query'] ?? [],
                    'ctxQuery' => $step['ctx_query'] ?? [],
                    'target' => $step['target'],
                    'advance' => $step['advance'],
                    'skipIf' => $step['skip_if'] ?? null,
                    'loop' => $step['loop'] ?? null,
                ], $tour['steps']),
            ];
        }

        // Chain each tour to the next one available, for "continue with…".
        $ids = array_values(array_intersect(self::ORDER, array_keys($tours)));
        foreach ($ids as $i => $id) {
            $tours[$id]['next'] = $ids[$i + 1] ?? null;
        }

        return $tours;
    }

    /**
     * Path templates of the routes a `route:` step can wait for, e.g.
     * `sites.show` → ['/sites/{site}', 'site'].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function routeTemplates(): array
    {
        $out = [];
        foreach (self::PARAM_ROUTES as $name => $param) {
            $out[$name] = [$this->template($name), $param];
        }

        return $out;
    }

    /**
     * "site.open" → "site_open": a dot would read as nesting to __().
     */
    private function langKey(string $key): string
    {
        return str_replace('.', '_', $key);
    }

    /**
     * Relative path with `{param}` placeholders the browser fills in.
     */
    private function template(string $route): string
    {
        $param = self::PARAM_ROUTES[$route] ?? null;
        if ($param === null) {
            return route($route, absolute: false);
        }

        return str_replace('__guide__', '{'.$param.'}', route($route, [$param => '__guide__'], absolute: false));
    }
}
