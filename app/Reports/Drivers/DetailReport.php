<?php

namespace App\Reports\Drivers;

use App\Filament\Components\DurationColumn;
use App\Filament\Exports\Reports\DetailReportExporter;
use App\Models\Reports\WorkActivityReport;
use App\Models\Snapshots\WorkTaskSubject;
use App\Services\DateRangeValidator;
use Carbon\CarbonInterval;
use Dpb\DatahubSync\Models\Department;
use Dpb\Departments\Services\DepartmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;

class DetailReport implements ReportDriver
{
    private ?DepartmentService $departmentService = null;

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

    public function getQuery(): Builder
    {
        return WorkActivityReport::query();
    }

    public function getColumns(): array
    {
        $departmentValues = $this->departmentService->getAvailableDepartments()->pluck('code');

        $subjectTypesWithDepartments = WorkTaskSubject::query()
            ->whereIn('department_code', $departmentValues)
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
                    $filterState = $livewire->getTableFilterState('department');
                    $selectedDepartments = $filterState['values'] ?? null;

                    // If no filter is applied, show the column
                    if (blank($selectedDepartments)) {
                        return false;
                    }

                    // Hide the column if the selected filter value is not in the allowed departments for this type
                    return empty(array_intersect($selectedDepartments, $allowedDepartments));
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
        $validator = new DateRangeValidator(120);

        return [
            Filter::make('date_range')
                ->form([
                    DatePicker::make('date_from')
                        ->label('Dátum od')
                        ->default(now()->subDays(120)->format('Y-m-d')) // Prefills 120 days ago
                        ->live(onBlur: true),
                    DatePicker::make('date_to')
                        ->label('Dátum do')
                        ->default(now()->format('Y-m-d')) // Prefills today
                        ->live(onBlur: true),
                    Placeholder::make('date_error')
                        ->content(function ($get) use ($validator) {
                            $from = $get('date_from');
                            $to = $get('date_to');
                            $validation = $validator->validate($from, $to);

                            if (! $validation['isValid']) {
                                return new HtmlString('
                                    <span style="color: red">
                                        '.$validation['error'].'
                                    </span>
                                ');
                            }

                            return '';
                        })
                        ->hiddenLabel(),
                ])
                ->query(function (Builder $query, array $data) use ($validator): Builder {
                    // Validate the date range before querying
                    if ($data['date_from'] && $data['date_to']) {
                        if (! $validator->validate($data['date_from'], $data['date_to'])['isValid']) {
                            return $query->whereRaw('1 = 0');
                        }
                    }

                    return $query
                        ->when(
                            $data['date_from'],
                            fn (Builder $query, $date): Builder => $query->where(
                                'activity_date',
                                '>=',
                                Carbon::parse($date)->startOfDay()
                            )
                        )
                        ->when(
                            $data['date_to'],
                            fn (Builder $query, $date): Builder => $query->where(
                                'activity_date',
                                '<=',
                                Carbon::parse($date)->endOfDay()
                            )
                        );
                })
                ->columns(2),

            // department
            SelectFilter::make('department')
                ->label(__('reports/detail-report.table.filters.department'))
                ->options(fn (DepartmentService $departmentSvc) => Department::whereIn('id', $departmentSvc->getAvailableDepartments()->pluck('id'))->pluck('code', 'code'))
                ->multiple()
                ->searchable()
                ->attribute('department_code'),
        ];
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
        return $query
            ->whereIn('department_code', $this->departmentService->getAvailableDepartments()->pluck('code'))
            ->with('taskSubjects');
    }

    public function lastSyncedAt(): ?string
    {
        return WorkActivityReport::getLastSyncedAt();
    }
}
