<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens move from the coarse read/write abilities to "{resource}:{action}"
 * and become admin-only: tokens owned by non-admins are revoked (down() can't
 * bring them back). The ability lists are frozen here on purpose, so later
 * changes to TokenAbilities don't alter what this migration did.
 */
return new class extends Migration
{
    private const RESOURCES = [
        'tenants' => ['read'],
        'sites' => ['read', 'create', 'update', 'delete'],
        'rooms' => ['read', 'create', 'update', 'delete'],
        'racks' => ['read', 'create', 'update', 'delete'],
        'equipment' => ['read', 'create', 'update', 'delete'],
        'interfaces' => ['read', 'create', 'update', 'delete'],
        'connections' => ['read', 'create', 'update', 'delete'],
        'topology' => ['read'],
    ];

    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->json('tenant_ids')->nullable()->after('abilities');
        });

        $adminIds = DB::table('users')->where('role', 'admin')->pluck('id');

        DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->whereNotIn('tokenable_id', $adminIds)
            ->delete();

        $read = [];
        $all = [];
        foreach (self::RESOURCES as $resource => $actions) {
            $read[] = "{$resource}:read";
            foreach ($actions as $action) {
                $all[] = "{$resource}:{$action}";
            }
        }

        DB::table('personal_access_tokens')->orderBy('id')->each(function (object $t) use ($read, $all): void {
            $old = json_decode((string) $t->abilities, true) ?: [];
            $new = array_intersect($old, ['write', '*']) !== [] ? [...$all, 'tokens:manage'] : $read;

            DB::table('personal_access_tokens')->where('id', $t->id)->update(['abilities' => json_encode($new)]);
        });
    }

    public function down(): void
    {
        DB::table('personal_access_tokens')->orderBy('id')->each(function (object $t): void {
            $old = json_decode((string) $t->abilities, true) ?: [];
            $writes = array_filter($old, fn (string $a): bool => ! str_ends_with($a, ':read'));

            DB::table('personal_access_tokens')->where('id', $t->id)
                ->update(['abilities' => json_encode($writes !== [] ? ['read', 'write'] : ['read'])]);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('tenant_ids');
        });
    }
};
