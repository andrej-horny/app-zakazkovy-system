<?php

namespace App\Snapshots\Services;

use App\Snapshots\Core\Contracts\SnapshotExecutionStrategy;
use App\Snapshots\Core\Snapshot;
use App\Snapshots\Core\SnapshotExecutionState;
use App\Snapshots\Core\SnapshotRunContext;
use App\Snapshots\Strategies\TempTableStrategy;
use Illuminate\Support\Facades\DB;

class WorkActivityReport extends Snapshot
{
    public function getKey(): string
    {
        return 'work-activity';
    }

    public function targetTable(): string
    {
        return 'mvw_work_activity_report_v2';
    }

    public function sourceTable(): string
    {
        return 'dpb_worktimefund_model_activityrecord';
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

    public function buildInsertSql(string $tempTable): string
    {
        return "
            INSERT INTO {$this->targetTable()}
                ({$this->columns()})
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

            // activity
            'activity_id' => 'ar.id',

            'personal_id' => 'hrc.pid',
            'last_name' => 'hrc.last_name',
            'first_name' => 'hrc.first_name',
            'department_code' => 'hrc.department_code',

            'activity_date' => 'ar.date',
            'activity_title' => 'ar.title',
            'activity_expected_duration' => 'ar.expected_duration',
            'activity_real_duration' => 'ar.real_duration',
            'activity_is_fulfilled' => 'ar.is_fulfilled',
            'activity_is_fulfilled_label' => 'CASE ar.is_fulfilled
                    WHEN 0 THEN "Nie"
                    WHEN 1 THEN "Áno"
                    ELSE "Nevyhodnotené"
                END',
            'activity_type' => 'NULL',
            'activity_is_tolerated' => 'NULL',
            'activity_sap_code' => 'CASE tis.task_group_code
                    WHEN "accident" THEN o.accident_sap_code
                    WHEN "malfunction" THEN o.malfunction_sap_code
                    WHEN "maintenance" THEN o.maintenance_sap_code
                    ELSE NULL
                END',

            // task snapshot
            'task_date' => 'tis.task_date',
            'task_group_title' => 'tis.task_group_title',
            'task_assigned_to_type' => 'tis.task_assigned_to_type',
            'task_assigned_to_label' => 'tis.task_assigned_to_label',
            'task_requested_for_type' => 'tis.task_requested_for_type',
            'task_requested_for_label' => 'tis.task_requested_for_label',
            'task_author_lastname' => 'tis.task_author_lastname',

            // task item snapshot
            'task_item_group_title' => 'tis.task_item_group_title',
            'task_item_assigned_to_type' => 'tis.task_item_assigned_to_type',
            'task_item_assigned_to_label' => 'tis.task_item_assigned_to_label',
            'task_item_author_lastname' => 'tis.task_item_author_lastname',

            // ids
            'task_id' => 'tis.task_id',
            'task_created_at' => 'tis.task_created_at',
            'task_item_id' => 'tis.task_item_id',
            'wtf_task_id' => 'wt.id',
            'wtf_task_created_at' => 'wt.created_at',

            // missing join placeholder
            'department_id' => 'wt.department_id',

            // audit
            'source_deleted_at' => 'ar.deleted_at',
            'source_updated_at' => 'ar.updated_at',
        ];
    }

    protected function from(string $tempTable): string
    {
        return "
            dpb_worktimefund_model_activityrecord ar
            JOIN {$tempTable} tmp ON tmp.id = ar.id
            LEFT JOIN dpb_worktimefund_model_task wt ON wt.id = ar.task_id
            LEFT JOIN dpb_wtftmsbridge_mm_workorder_task wot ON wot.taskitem_id = wt.id
            LEFT JOIN dpb_wtftmsbridge_model_workorder wo ON wo.id = wot.workorder_id
            LEFT JOIN mvw_task_item_snapshots tis ON tis.task_item_id = wo.tms_task_item_id
            LEFT JOIN mvw_hr_contract_snapshots hrc ON hrc.pid = ar.personal_id
            LEFT JOIN dpb_worktimefund_model_operation o ON o.id = wt.source_id
        ";
    }

    /**
     * @TODO
     */
    public function polymorphicContext(array $activityIds = []): string
    {
        $base = '
            SELECT
                ar.id as activity_id,
                ar.type as activity_type,
                t.id as task_id
            FROM dpb_worktimefund_model_activityrecord ar
            LEFT JOIN dpb_worktimefund_model_task t ON t.id = ar.task_id
        ';

        if (! empty($activityIds)) {
            $base .= ' WHERE ar.id IN ('.implode(',', $activityIds).')';
        }

        return $base;
    }
}
