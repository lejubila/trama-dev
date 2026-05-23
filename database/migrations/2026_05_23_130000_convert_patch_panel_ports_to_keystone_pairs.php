<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retrofit existing patch panels to the keystone-pair model.
     *
     * Patch-panel ports created before the front/rear pairing existed are
     * still recorded as a single interface (typically `type = ethernet`,
     * possibly `type = keystone` without a side). The new code expects every
     * port on a patch panel to be a pair: one row for the `front`, one for
     * the `rear`, cross-linked via paired_interface_id.
     *
     * The existing row is relabelled as the **rear** side — that is where
     * any pre-existing cable lands (the punch-down to the wall/dorsale) —
     * and a fresh `front` row is created with no connections.
     */
    public function up(): void
    {
        $candidates = DB::table('interfaces as i')
            ->join('equipment as e', 'e.id', '=', 'i.equipment_id')
            ->where('e.type', 'patch_panel')
            ->whereNull('i.paired_interface_id')
            ->select('i.*')
            ->get();

        foreach ($candidates as $row) {
            $now = now();

            $frontId = DB::table('interfaces')->insertGetId([
                'tenant_id' => $row->tenant_id,
                'equipment_id' => $row->equipment_id,
                'name' => $row->name,
                'type' => 'keystone',
                'side' => 'front',
                'paired_interface_id' => $row->id,
                'index' => $row->index,
                'speed_mbps' => $row->speed_mbps,
                'media' => $row->media,
                'connector' => $row->connector,
                'vlan_mode' => $row->vlan_mode,
                'vlan_default' => $row->vlan_default,
                'vlans_allowed' => $row->vlans_allowed,
                'ip_address' => null,
                'mac_address' => null,
                'status' => $row->status,
                'poe' => $row->poe,
                'description' => $row->description,
                'custom_fields' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('interfaces')->where('id', $row->id)->update([
                'type' => 'keystone',
                'side' => 'rear',
                'paired_interface_id' => $frontId,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Down migration removes the `front` rows we created and restores the
     * survivors. We can't faithfully recover the original `type` of the
     * relabelled rear rows; we set them back to `ethernet`, which was the
     * de-facto default before this change.
     */
    public function down(): void
    {
        $rears = DB::table('interfaces as i')
            ->join('equipment as e', 'e.id', '=', 'i.equipment_id')
            ->where('e.type', 'patch_panel')
            ->where('i.type', 'keystone')
            ->where('i.side', 'rear')
            ->whereNotNull('i.paired_interface_id')
            ->select('i.id', 'i.paired_interface_id')
            ->get();

        foreach ($rears as $rear) {
            DB::table('interfaces')->where('id', $rear->paired_interface_id)->delete();
            DB::table('interfaces')->where('id', $rear->id)->update([
                'type' => 'ethernet',
                'side' => null,
                'paired_interface_id' => null,
            ]);
        }
    }
};
