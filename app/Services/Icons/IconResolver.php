<?php

declare(strict_types=1);

namespace App\Services\Icons;

use App\Models\DeviceIcon;
use App\Models\Equipment;
use App\Models\Rack;

/**
 * Resolves the best icon image URL for a given record or kind, walking the
 * override chain: per-record → per-tenant → global → null (caller falls back
 * to the default shape).
 *
 * A per-request memoization avoids re-querying device_icons for every node
 * during a topology graph build or a room map render.
 */
class IconResolver
{
    /**
     * Cache of kind => image_path for a tenant scope. The outer key is the
     * tenant id ("global" for tenant_id IS NULL); inner key is the kind.
     *
     * @var array<string, array<string, string|null>>
     */
    private array $cache = [];

    public function urlForRack(Rack $rack, ?int $tenantId): ?string
    {
        if ($rack->icon_path !== null && $rack->icon_path !== '') {
            return $this->publicUrl($rack->icon_path);
        }

        return $this->urlForKind('rack', $tenantId);
    }

    public function urlForEquipment(Equipment $equipment, ?int $tenantId): ?string
    {
        if ($equipment->icon_path !== null && $equipment->icon_path !== '') {
            return $this->publicUrl($equipment->icon_path);
        }

        $kind = $equipment->type instanceof \BackedEnum
            ? (string) $equipment->type->value
            : (string) $equipment->type;

        return $this->urlForKind($kind, $tenantId);
    }

    public function urlForKind(string $kind, ?int $tenantId): ?string
    {
        // Prefer tenant-specific icon, then fall back to the global one.
        if ($tenantId !== null) {
            $tenantPath = $this->lookup((string) $tenantId, $kind, $tenantId);
            if ($tenantPath !== null) {
                return $this->publicUrl($tenantPath);
            }
        }

        $globalPath = $this->lookup('global', $kind, null);
        if ($globalPath !== null) {
            return $this->publicUrl($globalPath);
        }

        return null;
    }

    private function lookup(string $scopeKey, string $kind, ?int $tenantId): ?string
    {
        if (! isset($this->cache[$scopeKey])) {
            $query = DeviceIcon::query()->select(['kind', 'image_path']);
            if ($tenantId === null) {
                $query->whereNull('tenant_id');
            } else {
                $query->where('tenant_id', $tenantId);
            }
            $this->cache[$scopeKey] = $query->pluck('image_path', 'kind')->all();
        }

        return $this->cache[$scopeKey][$kind] ?? null;
    }

    private function publicUrl(string $path): string
    {
        // Root-relative on purpose: avoids APP_URL leaking the dev host.
        return '/storage/'.ltrim($path, '/');
    }
}
