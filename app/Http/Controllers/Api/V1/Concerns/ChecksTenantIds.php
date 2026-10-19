<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Foreign ids coming from the API must point to rows visible in the active
 * tenant *and* matching the domain constraint encoded in the query (e.g. only
 * firewall interfaces). Rejected with a 422 on the offending field instead of
 * a DB error or a silent drop.
 */
trait ChecksTenantIds
{
    /**
     * @param  Builder<*>  $eligible
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    protected function ensureIds(Builder $eligible, array $ids, string $field, string $message): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $found = $eligible->whereKey($ids)->pluck($eligible->getModel()->getQualifiedKeyName())->map(fn ($id): int => (int) $id)->all();
        $missing = array_values(array_diff($ids, $found));

        if ($missing !== []) {
            throw ValidationException::withMessages([$field => $message.' ('.implode(', ', $missing).')']);
        }

        return $ids;
    }

    /**
     * @param  Builder<*>  $eligible
     */
    protected function ensureId(Builder $eligible, int $id, string $field, string $message): int
    {
        return $this->ensureIds($eligible, [$id], $field, $message)[0];
    }
}
