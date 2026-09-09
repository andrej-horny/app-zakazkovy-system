<?php

namespace App\Reports\Drivers;

use App\Filament\Components\DurationColumn;
use App\Filament\Exports\Reports\DetailReportExporter;
use App\Models\Reports\WorkActivityReport;
use App\Models\Snapshots\WorkTaskSubject;
use Carbon\CarbonInterval;
use Dpb\DatahubSync\Models\Department;
use Dpb\Departments\Services\DepartmentService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class DetailReport implements ReportDriver
{
    private ?DepartmentService $departmentService = null;

    private ?Collection $permittedDepartmentIds = null;

    private ?Collection $permittedDepartmentCodes = null;

    public function __construct()
    {
        $this->departmentService = app(DepartmentService::class);
    }

    public function key(): string
    {
        return 'work-activity';
    }

    public function name(): string
    {
        return __('reports/detail-report.navigation.label');
    }

    public function getQuery($livewire): Builder
    {
        // Filters are provided by the shared header ReportFilters widget via the page.
        $pageFilters = $livewire->reportFilters ?? [];
        $departments = $pageFilters['departments'] ?? [];
        $dateFrom = $pageFilters['date_from'] ?? null;
        $dateTo = $pageFilters['date_to'] ?? null;

        // The view carries both department_id and department_code, so the integer
        // ids dispatched by the widget map straight onto department_id.
        return WorkActivityReport::query()
            // Always restrict to the departments the user is allowed to see.
            ->whereIn('department_id', $this->getPermittedDepartmentIds())
            // Additionally restrict to the selection made in the header widget
            // (only when something was selected; empty = all permitted ones).
            ->when($departments, function (Builder $query) use ($departments) {
                return $query->whereIn('department_id', $departments);
            })
            // Sargable, index-friendly range that includes the whole date_to day.
            ->when($dateFrom && $dateTo, function (Builder $query) use ($dateFrom, $dateTo) {
                $start = Carbon::parse($dateFrom)->startOfDay();
                $end = Carbon::parse($dateTo)->addDay()->startOfDay();

                return $query
                    ->where('activity_date', '>=', $start)
                    ->where('activity_date', '<', $end);
            });
    }

    public function getColumns(): array
    {
        $departmentCodes = $this->getPermittedDepartmentCodes();

        $subjectTypesWithDepartments = WorkTaskSubject::query()
            ->whereIn('department_code', $departmentCodes)
            ->select('subject_type', 'department_code')
            ->distinct()
            ->get()
            ->groupBy('subject_type')
            ->map(fn ($group) => $group->pluck('department_code')->toArray());

        $dynamicColumns = [];

        foreach ($subjectTypesWithDepartments as $type => $allowedDepartments) {
            $dynamicColumns[] = TextColumn::make("subject_{$type}")
                ->label(fn () => match ($type) {
                    'vehicle' => 'Vozidlo',
                    'table' => 'Tabuľa',
                    default => $type
                })
                ->getStateUsing(function ($record) use ($type) {
                    return $record->taskSubjects
                        ->where('subject_type', $type)
                        ->pluck('subject_label')
                        ->join(', ');
                })
                ->hidden(function ($livewire) use ($allowedDepartments) {
                    // Departments selected in the shared header ReportFilters
                    // widget are stored on the page as integer department ids.
                    $selectedDepartmentIds = $livewire->reportFilters['departments'] ?? [];

                    // If no departments are selected, show every applicable column.
                    if (blank($selectedDepartmentIds)) {
                        return false;
                    }

                    // Translate the selected ids back to codes so we can compare
                    // against the codes allowed for this subject type.
                    $selectedCodes = Department::query()
                        ->whereIn('id', $selectedDepartmentIds)
                        ->pluck('code')
                        ->toArray();

                    // Hide the column if none of the selected departments is
                    // allowed for this subject type.
                    return empty(array_intersect($selectedCodes, $allowedDepartments));
                });
        }

        return array_merge([
            TextColumn::make('activity_date')
                ->label(__('reports/detail-report.table.columns.activity_date'))
                ->date('Y-m-d'),
            TextColumn::make('personal_id')
                ->label(__('reports/detail-report.table.columns.personal_id')),
            TextColumn::make('full_name')
                ->label(__('reports/detail-report.table.columns.full_name')),
            TextColumn::make('department_code')
                ->label(__('reports/detail-report.table.columns.department_code')),
            TextColumn::make('task_group_title')
                ->label(__('reports/detail-report.table.columns.task_group_title')),
            TextColumn::make('task_item_group_title')
                ->label(__('reports/detail-report.table.columns.task_item_group_title'))
                ->limit(20)
                ->tooltip(fn ($record) => $record->task_item_group_title),
            TextColumn::make('activity_title')
                ->label(__('reports/detail-report.table.columns.activity_title'))
                ->limit(20)
                ->tooltip(fn ($record) => $record->activity_title),
            TextColumn::make('activity_expected_duration')
                ->label(__('reports/detail-report.table.columns.activity_expected_duration.label'))
                ->tooltip(__('reports/detail-report.table.columns.activity_expected_duration.tooltip'))
                ->formatStateUsing(function ($record) {
                    // Determine which value to use
                    $seconds = $record->activity_expected_duration >= 0
                        ? $record->activity_expected_duration
                        : $record->activity_real_duration;

                    if (! $seconds) {
                        return '0:00';
                    }

                    $interval = CarbonInterval::seconds($seconds)->cascade();

                    return sprintf('%d:%02d', floor($interval->totalHours), $interval->minutes);
                }),

            DurationColumn::make('activity_real_duration')
                ->label(__('reports/detail-report.table.columns.activity_real_duration.label'))
                ->tooltip(__('reports/detail-report.table.columns.activity_real_duration.tooltip')),
            TextColumn::make('activity_is_fulfilled_label')
                ->label(__('reports/detail-report.table.columns.activity_is_fulfilled')),
            TextColumn::make('task_id')
                ->label(__('reports/detail-report.table.columns.task_id'))
                ->url(
                    fn ($record) => $record->task_id
                        ? route('filament.admin.resources.task.task-assignments.edit', ['record' => $record->task_id])
                        : null
                )
                ->color(fn ($state) => $state ? 'primary' : 'gray')
                ->extraAttributes(fn ($state) => [
                    'class' => $state ? 'underline cursor-pointer' : '',
                ]),
            TextColumn::make('task_item_author_lastname')
                ->label(__('reports/detail-report.table.columns.task_item_author_lastname')),
        ], $dynamicColumns);
    }

    public function getFilters(): array
    {
        // Filtering is handled by the shared header ReportFilters widget and
        // applied directly inside getQuery(), so no inline table filters are needed.
        return [];
    }

    public function getSortColumn(): string
    {
        return 'activity_date';
    }

    public function getSortOrder(): string
    {
        return 'desc';
    }

    public function getExporter(): string
    {
        return DetailReportExporter::class;
    }

    public function generateExportFilename(): string
    {
        return 'work_activity_'.now()->format('Ymd_His').'.xlsx';
    }

    public function applyQueryModifications(Builder $query): Builder
    {
        // Defense in depth. Redundant with the subquery/where-in filters but
        // cheap, and it preserves the taskSubjects eager loading that the
        // dynamic subject columns depend on.
        return $query
            ->whereIn('department_id', $this->getPermittedDepartmentIds())
            ->with('taskSubjects');
    }

    public function lastSyncedAt(): ?string
    {
        return WorkActivityReport::getLastSyncedAt();
    }

    /**
     * IDs of the departments the current user is allowed to view.
     *
     * @return Collection<int, int>
     */
    private function getPermittedDepartmentIds(): Collection
    {
        return $this->permittedDepartmentIds ??= $this->departmentService
            ->getAvailableDepartments()
            ->pluck('id');
    }

    /**
     * Codes of the departments the current user is allowed to view.
     *
     * @return Collection<int, string>
     */
    private function getPermittedDepartmentCodes(): Collection
    {
        return $this->permittedDepartmentCodes ??= $this->departmentService
            ->getAvailableDepartments()
            ->pluck('code');
    }
}

