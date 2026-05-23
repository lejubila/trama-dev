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
        Schema::table('interfaces', function (Blueprint $table) {
            $table->string('side', 10)->nullable()->after('type');
            $table->foreignId('paired_interface_id')
                ->nullable()
                ->after('side')
                ->constrained('interfaces')
                ->nullOnDelete();
            $table->index(['equipment_id', 'side']);
        });

        // Backfill: every existing keystone interface becomes the "front" side
        // of a pair; we create a sibling "rear" row with the same descriptive
        // attributes (name/index/connector/etc.) and cross-link the pair.
        $keystones = DB::table('interfaces')
            ->where('type', 'keystone')
            ->whereNull('side')
            ->get();

        foreach ($keystones as $front) {
            $now = now();
            $rearId = DB::table('interfaces')->insertGetId([
                'tenant_id' => $front->tenant_id,
                'equipment_id' => $front->equipment_id,
                'name' => $front->name,
                'type' => 'keystone',
                'side' => 'rear',
                'paired_interface_id' => $front->id,
                'index' => $front->index,
                'speed_mbps' => $front->speed_mbps,
                'media' => $front->media,
                'connector' => $front->connector,
                'vlan_mode' => $front->vlan_mode,
                'vlan_default' => $front->vlan_default,
                'vlans_allowed' => $front->vlans_allowed,
                'ip_address' => null,
                'mac_address' => null,
                'status' => $front->status,
                'poe' => $front->poe,
                'description' => $front->description,
                'custom_fields' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('interfaces')->where('id', $front->id)->update([
                'side' => 'front',
                'paired_interface_id' => $rearId,
                'updated_at' => $now,
            ]);
        }

        // Drop the legacy (equipment_id, name) unique: a keystone port now has
        // two rows sharing the name (one per side). Replace with a constraint
        // that scopes uniqueness by side, treating non-keystone interfaces
        // (side IS NULL) as a single namespace.
        DB::statement('ALTER TABLE interfaces DROP CONSTRAINT IF EXISTS interfaces_equipment_id_name_unique');
        DB::statement("
            CREATE UNIQUE INDEX interfaces_equipment_name_side_unique
              ON interfaces (equipment_id, name, COALESCE(side, ''))
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS interfaces_equipment_name_side_unique');

        // Remove the auto-generated rear rows so we can restore the
        // (equipment_id, name) unique without conflict.
        DB::table('interfaces')->where('side', 'rear')->delete();

        Schema::table('interfaces', function (Blueprint $table) {
            $table->dropIndex(['equipment_id', 'side']);
            $table->dropConstrainedForeignId('paired_interface_id');
            $table->dropColumn('side');
        });

        Schema::table('interfaces', function (Blueprint $table) {
            $table->unique(['equipment_id', 'name']);
        });
    }
};
