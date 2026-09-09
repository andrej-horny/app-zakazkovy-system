<?php

namespace App\Filament\Exports\Reports;

use App\Reports\Exports\BaseReportExporter;
use Dpb\Departments\Services\DepartmentService;
use Illuminate\Support\Facades\DB;

class DetailReportExporter extends BaseReportExporter
{
    private ?DepartmentService $departmentService = null;

    private array $filters = [];

    public function __construct()
    {
        parent::__construct();
        $this->departmentService = app(DepartmentService::class);
    }

    protected const DYNAMIC_COLUMN_PREFIX = 'dc_';

    protected const SUBJECT_TABLE_ALIAS = 'wtss';

    protected function dynamicColumns(): array
    {
        $departmentCodes = data_get($this->filters, 'department.values');
        if (empty($departmentCodes)) {
            $departmentCodes = $this->departmentService->getAvailableDepartments()->pluck('code')->toArray();
        }
        $subjectTypes = DB::table('mvw_work_task_subject_snapshots AS wtss')
            ->whereIn('wtss.department_code', $departmentCodes)
            ->distinct()
            ->pluck('wtss.subject_type')
            ->toArray();

        $columns = [];

        foreach ($subjectTypes as $key => $type) {
            $columns[] = [
                'key' => self::DYNAMIC_COLUMN_PREFIX.$key,
                'label' => $type,
                'static' => false,
                'definition' => DB::raw('
                    MAX(CASE 
                        WHEN '.self::SUBJECT_TABLE_ALIAS.".subject_type = '$type' 
                        THEN ".self::SUBJECT_TABLE_ALIAS.'.subject_label 
                    END) AS '.self::DYNAMIC_COLUMN_PREFIX.$key
                ),
            ];
        }

        return $columns;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'ar.department_code', 'label' => 'Stredisko'],
            ['key' => 'task_created_at', 'label' => 'Čas vytvorenia zákazky'],
            ['key' => 'activity_is_fulfilled_label', 'label' => 'Splnené'],
            ['key' => 'task_date', 'label' => 'Dátum zákazky', 'type' => 'date'],
            ['key' => 'task_group_title', 'label' => 'Typ zákazky'],
            ['key' => 'task_assigned_to_label', 'label' => 'Prevádzka zákazky'],
            ['key' => 'task_author_lastname', 'label' => 'Zákzaku vytvoril'],
            ['key' => 'task_item_group_title', 'label' => 'Typ podzákazky'],
            ['key' => 'task_item_assigned_to_label', 'label' => 'Prevádzka podzákazky'],
            ['key' => 'task_item_author_lastname', 'label' => 'Podzákazku vytvoril'],
            ['key' => 'wtf_task_created_at', 'label' => 'Čas priradenia práce'],
            ['key' => 'activity_date', 'label' => 'Dátum výkonu práce', 'type' => 'date'],
            ['key' => 'personal_id', 'label' => 'Osobné číslo'],
            ['key' => 'last_name', 'label' => 'Priezvisko'],
            ['key' => 'first_name', 'label' => 'Meno'],
            ['key' => 'activity_title', 'label' => 'Norma', 'width' => 35],
            ['key' => 'activity_expected_duration', 'label' => 'Norma trvanie', 'type' => 'duration'],
            ['key' => 'activity_real_duration', 'label' => 'Reálne trvanie', 'type' => 'duration'],
            ['key' => 'activity_is_fulfilled_label', 'label' => 'Splnené'],
            ...$this->dynamicColumns(),
        ];
    }

    protected function query(array $filters)
    {
        $this->filters = $filters;
        $departmentCodes = data_get($filters, 'department.values');
        $staticColumns = array_column(
            array_filter($this->columns(), fn ($c) => ($c['static'] ?? true)),
            'key'
        );
        $dynamicColumns = array_column(
            array_filter($this->columns(), fn ($c) => (isset($c['static']) && $c['static'] == false)),
            'definition'
        );

        $result = DB::table('mvw_work_activity_report_v2 AS ar')
            ->leftJoin('mvw_work_task_subject_snapshots AS '.self::SUBJECT_TABLE_ALIAS, self::SUBJECT_TABLE_ALIAS.'.wtf_task_id', '=', 'ar.wtf_task_id')
            ->select([
                'ar.id',
                ...$staticColumns,
                ...$dynamicColumns,
            ])
            ->groupBy([
                'ar.id',
                ...$staticColumns,
            ])
            ->when(data_get($filters, 'date_range.date_from'), fn ($q, $v) => $q->whereDate('activity_date', '>=', $v))
            ->when(data_get($filters, 'date_range.date_to'), fn ($q, $v) => $q->whereDate('activity_date', '<=', $v))
            ->when(! empty($departmentCodes), function ($q) use ($departmentCodes) {
                $q->whereIn('ar.department_code', $departmentCodes);
            })
            // Always apply available departments filter (same as applyQueryModifications)
            ->whereIn('ar.department_code', $this->departmentService->getAvailableDepartments()->pluck('code'));

        return $result;
    }

    public function getExporterName(): string
    {
        return 'XLSWriter-Remote';
    }
}
