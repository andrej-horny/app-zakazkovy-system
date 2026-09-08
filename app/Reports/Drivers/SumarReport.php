<?php

namespace App\Reports\Drivers;

use App\Filament\Components\DurationColumn;
use App\Filament\Exports\Reports\SumarReportExporter;
use App\Models\Reports\WorktimeFundPerformanceReport;
use App\Services\DateRangeValidator;
use Dpb\DatahubSync\Models\Department;
use Dpb\Departments\Services\DepartmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Carbon;

class SumarReport implements ReportDriver
{
    private ?DepartmentService $departmentService = null;

    public function __construct()
    {
        $this->departmentService = app(DepartmentService::class);
    }

    public function key(): string
    {
        return 'sumar';
    }

    public function name(): string
    {
        return 'Práce sumár';
    }

    public function getQuery(): Builder
    {
        return WorktimeFundPerformanceReport::query()
            ->select([
                'd.code as stredisko',
                new Expression('
                    ROW_NUMBER() OVER (ORDER BY c.pid) as id
                '),
                new Expression("TRIM(LEADING '0' FROM c.pid) as osob_cislo"),
                new Expression("CONCAT(wt.last_name, ' ', wt.first_name) AS meno"),
                new Expression('SUM(dpb_worktimefund_model_activityrecord.real_duration) AS suma_cas_skutocny'),
                new Expression('SUM(dpb_worktimefund_model_activityrecord.expected_duration) AS suma_cas_norma'),
                new Expression('ROUND(100 * SUM(dpb_worktimefund_model_activityrecord.expected_duration) / SUM(dpb_worktimefund_model_activityrecord.real_duration), 0) AS plnenie'),
            ])
            ->leftJoin('dpb_worktimefund_model_worktime as wt', 'wt.id', '=', 'dpb_worktimefund_model_activityrecord.parent_id')
            ->leftJoin('datahub_employee_contracts as c', 'c.pid', '=', 'wt.personal_id')
            ->leftJoin('datahub_departments as d', 'd.id', '=', 'c.datahub_department_id')
            ->where('dpb_worktimefund_model_activityrecord.type', 'O')
            ->groupBy('c.pid', 'wt.last_name', 'wt.first_name', 'd.code');
    }

    public function getColumns(): array
    {
        return [
            TextColumn::make('stredisko')
                ->label('Stredisko'),
            TextColumn::make('osob_cislo')
                ->label('Osobné číslo'),
            TextColumn::make('meno')
                ->label('Meno'),
            DurationColumn::make('suma_cas_skutocny')
                ->label('Čas skutočný'),
            DurationColumn::make('suma_cas_norma')
                ->label('Čas norma'),
            TextColumn::make('plnenie')
                ->label('Plnenie (%)'),
        ];
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
                    if ($data['date_from'] && $data['date_to']) {
                        if (! $validator->validate($data['date_from'], $data['date_to'])['isValid']) {
                            return $query->whereRaw('1 = 0');
                        }
                    }

                    return $query
                        ->when(
                            $data['date_from'],
                            fn (Builder $query, $date): Builder => $query->where('dpb_worktimefund_model_activityrecord.date', '>=', Carbon::parse($date)->startOfDay()),
                        )
                        ->when(
                            $data['date_to'],
                            fn (Builder $query, $date): Builder => $query->where('dpb_worktimefund_model_activityrecord.date', '<=', Carbon::parse($date)->endOfDay()),
                        );
                })->columns(2),

            SelectFilter::make('department')
                ->label(__('reports/detail-report.table.filters.department'))
                ->options(fn (DepartmentService $departmentSvc) => Department::whereIn('id', $departmentSvc->getAvailableDepartments()->pluck('id'))->pluck('code', 'code'))
                ->multiple()
                ->searchable()
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['values'],
                        fn (Builder $query, $values): Builder => $query->whereIn('d.code', $values)
                    );
                }),
        ];
    }

    public function getSortColumn(): string
    {
        return 'id';
    }

    public function getSortOrder(): string
    {
        return 'asc';
    }

    public function getExporter(): string
    {
        return SumarReportExporter::class;
    }

    public function generateExportFilename(): string
    {
        return 'sumar_'.now()->format('Ymd_His').'.xlsx';
    }

    public function applyQueryModifications(Builder $query): Builder
    {
        return $query->whereIn('d.code', $this->departmentService->getAvailableDepartments()->pluck('code'));
    }

    public function lastSyncedAt(): ?string
    {
        return 'teraz';
    }
}
