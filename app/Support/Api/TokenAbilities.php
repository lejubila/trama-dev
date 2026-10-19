<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * Single registry of what an API token may be allowed to do. Abilities are
 * stored by Sanctum as "{resource}:{action}" strings; the token can only ever
 * narrow what its (admin) owner may do — Policies still apply on top.
 */
final class TokenAbilities
{
    public const READ = 'read';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const ACTIONS = [self::READ, self::CREATE, self::UPDATE, self::DELETE];

    /** Lets the token manage other tokens through the API (never granted by presets). */
    public const MANAGE_TOKENS = 'tokens:manage';

    /** @var array<string, list<string>> */
    public const RESOURCES = [
        'tenants' => [self::READ],
        'sites' => self::ACTIONS,
        'rooms' => self::ACTIONS,
        'racks' => self::ACTIONS,
        'equipment' => self::ACTIONS,
        'interfaces' => self::ACTIONS,
        'connections' => self::ACTIONS,
        'topology' => [self::READ],
    ];

    /** Route-name action segment → ability action. */
    private const ROUTE_ACTIONS = [
        'index' => self::READ,
        'show' => self::READ,
        'store' => self::CREATE,
        'update' => self::UPDATE,
        'destroy' => self::DELETE,
    ];

    public static function make(string $resource, string $action): string
    {
        return "{$resource}:{$action}";
    }

    /**
     * Every resource ability (excluding the special tokens:manage).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::RESOURCES as $resource => $actions) {
            foreach ($actions as $action) {
                $out[] = self::make($resource, $action);
            }
        }

        return $out;
    }

    /**
     * Abilities a token may be granted, including tokens:manage.
     *
     * @return list<string>
     */
    public static function grantable(): array
    {
        return [...self::all(), self::MANAGE_TOKENS];
    }

    /**
     * @return list<string>
     */
    public static function readOnly(): array
    {
        return array_map(fn (string $r): string => self::make($r, self::READ), array_keys(self::RESOURCES));
    }

    /**
     * Ability required by a named API route, or null when the route is not
     * mapped (callers must then deny). Names look like "api.v1.equipment.show",
     * "api.v1.sites.rooms.index" (resource = rooms) or "api.v1.topology".
     */
    public static function forRoute(?string $routeName): ?string
    {
        if ($routeName === null || ! str_starts_with($routeName, 'api.v1.')) {
            return null;
        }

        $segments = explode('.', substr($routeName, strlen('api.v1.')));
        $last = array_pop($segments);

        if (isset(self::ROUTE_ACTIONS[$last]) && $segments !== []) {
            $resource = end($segments);
            $action = self::ROUTE_ACTIONS[$last];
        } else {
            // Single-action routes such as "topology".
            $resource = $last;
            $action = self::READ;
        }

        return in_array($action, self::RESOURCES[$resource] ?? [], true)
            ? self::make($resource, $action)
            : null;
    }

    /**
     * Keeps only known abilities, adding "read" wherever a write action is
     * granted on the same resource (writing blind makes no sense).
     *
     * @param  array<int, string>  $abilities
     * @return list<string>
     */
    public static function normalize(array $abilities): array
    {
        $abilities = array_values(array_intersect(self::grantable(), $abilities));

        foreach ($abilities as $ability) {
            [$resource] = explode(':', $ability, 2);
            if (isset(self::RESOURCES[$resource])) {
                $abilities[] = self::make($resource, self::READ);
            }
        }

        return array_values(array_intersect(self::grantable(), array_unique($abilities)));
    }
}
