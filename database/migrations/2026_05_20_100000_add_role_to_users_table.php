<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('cliente')->after('email');
        });

        // Backfill the global role from the highest role the user holds across
        // any tenant_user pivot row (admin > tecnico > cliente). Done only if the
        // legacy pivot column still exists (fresh installs won't have it).
        if (Schema::hasColumn('tenant_user', 'role')) {
            $ranks = ['admin' => 3, 'tecnico' => 2, 'cliente' => 1];

            $rows = DB::table('tenant_user')
                ->select('user_id', 'role')
                ->get()
                ->groupBy('user_id');

            foreach ($rows as $userId => $pivots) {
                $best = 'cliente';
                foreach ($pivots as $pivot) {
                    if (($ranks[$pivot->role] ?? 0) > ($ranks[$best] ?? 0)) {
                        $best = $pivot->role;
                    }
                }

                DB::table('users')->where('id', $userId)->update(['role' => $best]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
