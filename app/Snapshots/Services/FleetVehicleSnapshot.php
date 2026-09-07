<?php

namespace App\Snapshots\Services;

use App\Snapshots\Core\Contracts\SnapshotExecutionStrategy;
use App\Snapshots\Core\Snapshot;
use App\Snapshots\Core\SnapshotExecutionState;
use App\Snapshots\Core\SnapshotRunContext;
use App\Snapshots\Strategies\TempTableStrategy;
use Illuminate\Support\Facades\DB;

class FleetVehicleSnapshot extends Snapshot
{
    public function getKey(): string
    {
        return 'fleet-vehicle';
    }

    public function targetTable(): string
    {
        return 'mvw_fleet_vehicle_snapshots';
    }

    public function sourceTable(): string
    {
        return 'fleet_vehicles';
    }

    public function strategy(): SnapshotExecutionStrategy
    {
        return app(TempTableStrategy::class);
    }

    public function run(SnapshotRunContext $context, SnapshotExecutionState $state): void
    {
        DB::statement(
            $this->buildInsertSql(
                $state->tempTable,
            )
        );
    }

    public function vehicleLatestCodeCTE(string $uri = 'latest_code'): string
    {
        return "
            {$uri} AS (
                SELECT vehicle_id, vehicle_code_id
                FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY vehicle_id ORDER BY date_from DESC) rn
                    FROM fleet_vehicle_code_history
                ) x
                WHERE rn = 1
            )
        ";
    }

    public function vehicleLatestPlateCTE(string $uri = 'latest_plate'): string
    {
        return "
            {$uri} AS (
                SELECT vehicle_id, licence_plate_id
                FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY vehicle_id ORDER BY date_from DESC) rn
                    FROM fleet_licence_plate_history
                ) x
                WHERE rn = 1
            )
        ";
    }

    protected function with(string $tempTable): string
    {
        return "
            {$this->vehicleLatestCodeCTE()},
            {$this->vehicleLatestPlateCTE()}
        ";
    }

    public function buildInsertSql(string $tempTable): string
    {
        return "
            INSERT INTO {$this->targetTable()}
                ({$this->columns()})
            WITH 
                {$this->with($tempTable)}   
            SELECT
                {$this->select()}
            FROM
                {$this->from($tempTable)}
            ON DUPLICATE KEY UPDATE
                {$this->duplicateKeyUpdate('activity_id')}
        ";
    }

    protected function map(): array
    {
        return [
            'vin' => 'v.vin',
            'vehicle_id' => 'v.id',
            'code' => 'vc.`code`',
            'licence_plate' => 'lp.`code`',
            'model' => 'vm.title',
            'type' => 'vt.title',
            'maintenance_group_code' => 'mg.code',
            'maintenance_group_title' => 'mg.title',
            'length' => 'vm.length',
            'seats' => 'vm.seats',
            'maintenance_group_id' => 'mg.id',
            'model_id' => 'vm.id',
            'type_id' => 'vt.id',
            'updated_at' => 'v.updated_at',
        ];
    }

    protected function from(string $tempTable): string
    {
        return "
            fleet_vehicles v
            JOIN {$tempTable} tmp ON tmp.id = v.id
            LEFT JOIN fleet_vehicle_models vm ON vm.id = v.model_id
            LEFT JOIN fleet_vehicle_types vt ON vt.id = vm.type_id
            LEFT JOIN latest_code lc ON lc.vehicle_id = v.id
            LEFT JOIN fleet_vehicle_codes vc ON vc.id = lc.vehicle_code_id
            LEFT JOIN latest_plate lp_ref ON lp_ref.vehicle_id = v.id
            LEFT JOIN fleet_licence_plates lp ON lp.id = lp_ref.licence_plate_id
            LEFT JOIN fleet_maintenance_groups mg ON mg.id = v.maintenance_group_id
        ";
    }
}
