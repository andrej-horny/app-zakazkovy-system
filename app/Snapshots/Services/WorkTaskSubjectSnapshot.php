<?php

namespace App\Snapshots\Services;

use App\Snapshots\Core\Contracts\SnapshotExecutionStrategy;
use App\Snapshots\Core\Snapshot;
use App\Snapshots\Core\SnapshotExecutionState;
use App\Snapshots\Core\SnapshotRunContext;
use App\Snapshots\Strategies\TempTableStrategy;
use Illuminate\Support\Facades\DB;

class WorkTaskSubjectSnapshot extends Snapshot
{
    public function getKey(): string
    {
        return 'work-task-subject';
    }

    public function targetTable(): string
    {
        return 'mvw_work_task_subject_snapshots';
    }

    public function sourceTable(): string
    {
        return 'dpb_worktimefund_model_task';
    }

    public function strategy(): SnapshotExecutionStrategy
    {
        return app(TempTableStrategy::class);
    }

    protected function resolvedSubjectTypeCTE(string $tempTable): string
    {
        return "
            subject_base AS (
                SELECT
                    t.id AS task_id,
                    CASE
                        WHEN t.maintainable_type = 'Dpb\\\\WorkTimeFund\\\\Models\\\\Maintainables\\\\Vehicle' THEN 'Vozidlo'
                        WHEN t.maintainable_type = 'Dpb\\\\WorkTimeFund\\\\Models\\\\Maintainables\\\\Table' THEN 'Zastávková tabuľa'
                        ELSE NULL
                    END AS type,
                    t.maintainable_id AS entity_id,
                    t.department_id AS department_id,
                    t.updated_at as updated_at
                FROM dpb_worktimefund_model_task t
                    JOIN {$tempTable} tmp ON tmp.id = t.id
            ),
            subject_resolved AS (
                SELECT
                    sb.task_id,
                    COALESCE(sb.type, at.title) as type,
                    COALESCE(
                        CASE WHEN sb.type = 'Vozidlo' THEN COALESCE(fvs.code, fvs.licence_plate, 'N/A') END,
                        tr.label, 
                        ao.label
                    ) AS label,
                    deps.code AS department_code,
                    sb.updated_at
                FROM subject_base sb
                    LEFT JOIN fleet_vehicles v 
                        ON v.id = sb.entity_id AND sb.type = 'Vozidlo'
                    LEFT JOIN mvw_fleet_vehicle_snapshots fvs 
                        ON fvs.vehicle_id = v.id AND sb.type = 'Vozidlo'
                    JOIN datahub_departments deps ON deps.id = sb.department_id
                    LEFT JOIN table_resolved tr ON tr.device_id = sb.entity_id AND sb.type = 'Zastávková tabuľa'
                    LEFT JOIN dpb_worktimefund_mm_morphable_attributeoption mao ON mao.morphable_id = sb.task_id AND mao.morphable_type = 'Dpb\\\\WorkTimeFund\\\\Models\\\\Task'
                    LEFT JOIN dpb_worktimefund_model_attributeoption ao ON ao.id = mao.attributeoption_id
                    LEFT JOIN dpb_worktimefund_model_attributetype at ON at.id = ao.attributetype_id     
            )        
        ";
    }

    protected function resolvedTableCTE(): string
    {
        return '
            latest_location AS (
                SELECT device_id, location_id
                FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY device_id ORDER BY date_from DESC) rn
                    FROM dvc_device_location_history
                ) x
                WHERE rn = 1
            ),
            table_resolved AS (
                SELECT
                    d.id AS device_id,
                    dl.title AS label
                FROM dvc_devices d
                    LEFT JOIN latest_location ll ON ll.device_id = d.id
                    LEFT JOIN dvc_device_locations dl ON dl.id = ll.location_id
            )        
        ';
    }

    protected function with(string $tempTable): string
    {
        return "
            {$this->resolvedTableCTE()},
            {$this->resolvedSubjectTypeCTE($tempTable)}
        ";
    }

    public function idQuery(SnapshotRunContext $context)
    {
        // dd($context->from);
        return DB::table($this->sourceTable())
            ->where('updated_at', '>', $context->from)
            ->select('id');
    }

    public function run(SnapshotRunContext $context, SnapshotExecutionState $state): void
    {
        DB::statement(
            $this->buildInsertSql(
                $state->tempTable,
            )
        );
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
                {$this->from()}
            WHERE sr.type IS NOT NULL
            ON DUPLICATE KEY UPDATE
                {$this->duplicateKeyUpdate('activity_id')}
        ";
    }

    protected function map(): array
    {
        return [
            'wtf_task_id' => 'sr.task_id',
            'subject_type' => 'sr.type',
            'subject_label' => 'sr.label',
            'department_code' => 'sr.department_code',
            'updated_at' => 'sr.updated_at',
        ];
    }

    protected function from(): string
    {
        return '
            subject_resolved sr
        ';
    }
}
