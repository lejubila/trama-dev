<?php

declare(strict_types=1);

namespace App\Services\TenantTransfer;

/**
 * Documents and topology snapshots store database ids inside JSON blobs.
 * After an import every id changes, so those blobs are rewritten here.
 * Ids that no longer exist are dropped rather than left dangling: they
 * would otherwise silently point at another tenant's rows.
 */
class ViewStateRemapper
{
    /** Cytoscape node id prefixes, as built by TopologyService. */
    private const NODE_PREFIXES = [
        'vpn-stos' => 'vpn_site_to_site',
        'vpn-ra' => 'vpn_remote_access',
        'wifi' => 'wifi_networks',
        'host' => 'equipment',
        'eq' => 'equipment',
        'rack' => 'racks',
        'room' => 'rooms',
        'site' => 'sites',
    ];

    /**
     * @param  array<string, array<int, int>>  $maps  section => [old id => new id]
     */
    public function __construct(private readonly array $maps) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function documentParameters(array $params): array
    {
        $sections = $params['sections'] ?? null;
        if (! is_array($sections)) {
            return $params;
        }

        foreach (['sites' => 'sites', 'rooms' => 'rooms', 'racks' => 'racks', 'equipment' => 'equipment'] as $key => $map) {
            if (is_array($sections[$key]['ids'] ?? null)) {
                $sections[$key]['ids'] = $this->ids($sections[$key]['ids'], $map);
            }
        }
        if (is_array($sections['equipment']['ports_excluded'] ?? null)) {
            $sections['equipment']['ports_excluded'] = $this->ids($sections['equipment']['ports_excluded'], 'interfaces');
        }
        if (is_array($sections['topologies']['items'] ?? null)) {
            $items = [];
            foreach ($sections['topologies']['items'] as $item) {
                $new = is_array($item) ? $this->id($item['id'] ?? null, 'topology_snapshots') : null;
                if ($new !== null) {
                    $items[] = ['id' => $new] + $item;
                }
            }
            $sections['topologies']['items'] = $items;
        }

        $params['sections'] = $sections;

        return $params;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function viewState(array $state): array
    {
        foreach (['siteId' => 'sites', 'roomFilter' => 'rooms'] as $key => $map) {
            if (! empty($state[$key])) {
                $state[$key] = $this->id($state[$key], $map) ?? 0;
            }
        }

        if (is_array($state['tagFilters'] ?? null)) {
            // The UI keeps tag ids as strings; preserve that shape.
            $state['tagFilters'] = array_map('strval', $this->ids($state['tagFilters'], 'tags'));
        }
        if (is_array($state['sessionHiddenIds'] ?? null)) {
            $state['sessionHiddenIds'] = $this->ids($state['sessionHiddenIds'], 'equipment');
        }
        if (is_array($state['portSettings'] ?? null)) {
            $state['portSettings'] = $this->keys($state['portSettings'], fn ($k) => $this->id($k, 'interfaces'));
        }
        foreach (['nodePositions', 'nodeLabelPositions', 'vpnNodeDetails'] as $key) {
            if (is_array($state[$key] ?? null)) {
                $state[$key] = $this->keys($state[$key], fn ($k) => $this->nodeId((string) $k));
            }
        }

        return $state;
    }

    /**
     * Unknown node formats are kept untouched (they carry no tenant id);
     * known formats with a vanished id are dropped.
     */
    private function nodeId(string $nodeId): ?string
    {
        foreach (self::NODE_PREFIXES as $prefix => $map) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $nodeId, $m) === 1) {
                $new = $this->id($m[1], $map);

                return $new === null ? null : "{$prefix}-{$new}";
            }
        }

        return $nodeId;
    }

    /**
     * @param  array<int|string, mixed>  $assoc
     * @param  callable(int|string): (int|string|null)  $mapKey
     * @return array<int|string, mixed>
     */
    private function keys(array $assoc, callable $mapKey): array
    {
        $out = [];
        foreach ($assoc as $k => $v) {
            $new = $mapKey($k);
            if ($new !== null) {
                $out[$new] = $v;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function ids(array $ids, string $map): array
    {
        return array_values(array_filter(
            array_map(fn ($id) => $this->id($id, $map), $ids),
            fn ($id) => $id !== null,
        ));
    }

    private function id(mixed $old, string $map): ?int
    {
        if (! is_numeric($old)) {
            return null;
        }

        return $this->maps[$map][(int) $old] ?? null;
    }
}
