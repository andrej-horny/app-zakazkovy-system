<?php

namespace App\Reports\Drivers;

use App\Filament\Components\DurationColumn;
use App\Filament\Exports\Reports\SumarReportExporter;
use App\Models\Reports\WorktimeFundPerformanceReport;
use Carbon\Carbon;
use Dpb\Departments\Services\DepartmentService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SumarReport implements ReportDriver
{
    private ?DepartmentService $departmentService = null;

    private ?Collection $availableDepartmentIds = null;

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

    public function getQuery($livewire): Builder
    {
        $pageFilters = $livewire->reportFilters ?? [];
        $departments = $pageFilters['departments'] ?? [];
        $dateFrom = $pageFilters['date_from'] ?? null;
        $dateTo = $pageFilters['date_to'] ?? null;

        $permittedDepartmentIds = $this->getPermittedDepartmentIds();

        $aggregated = WorktimeFundPerformanceReport::query()
            ->select([
                'department_id',
                'personal_id',
                DB::raw('SUM(real_duration) AS suma_cas_skutocny'),
                DB::raw('SUM(expected_duration) AS suma_cas_norma'),
            ])
            ->where('type', 'O')

            ->whereIn('department_id', $permittedDepartmentIds)

            ->when($departments, function ($query) use ($departments) {
                return $query->whereIn('department_id', $departments);
            })
            ->when($dateFrom && $dateTo, function ($query) use ($dateFrom, $dateTo) {

                $start = Carbon::parse($dateFrom)->startOfDay();
                $end = Carbon::parse($dateTo)->addDay()->startOfDay();

                return $query
                    ->where('date', '>=', $start)
                    ->where('date', '<', $end);
            })
            ->groupBy('department_id', 'personal_id');

        return WorktimeFundPerformanceReport::query()
            ->fromSub($aggregated, 'a')
            ->leftJoin(
                'datahub_employee_contracts as c',
                'c.pid',
                '=',
                'a.personal_id'
            )
            ->leftJoin(
                'datahub_employees as de',
                'de.id',
                '=',
                'c.datahub_employee_id'
            )
            ->leftJoin(
                'datahub_departments as d',
                'd.id',
                '=',
                'a.department_id'
            )
            ->select([
                'd.code as stredisko',
                DB::raw('ROW_NUMBER() OVER (ORDER BY c.pid) as id'),
                DB::raw("TRIM(LEADING '0' FROM c.pid) as osob_cislo"),
                DB::raw("CONCAT(de.last_name, ' ', de.first_name) AS meno"),
                'a.suma_cas_skutocny',
                'a.suma_cas_norma',
                DB::raw('ROUND(
                    100 * a.suma_cas_norma / NULLIF(a.suma_cas_skutocny, 0),
                    0
                ) AS plnenie'),
            ]);
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
        return [];
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
        return $query;
    }

    private function getPermittedDepartmentIds(): Collection
    {
        return $this->availableDepartmentIds ??= $this->departmentService
            ->getAvailableDepartments()
            ->pluck('id');
    }

    public function lastSyncedAt(): ?string
    {
        return 'teraz';
    }
}

