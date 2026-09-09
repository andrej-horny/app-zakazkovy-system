<?php

namespace App\Filament\Resources\ReportsResource\Pages;

use App\Filament\Resources\ReportsResource;
use App\Jobs\Reports\ExportReportJob;
use App\Reports\ReportFactory;
use App\Services\DateRangeValidator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use App\Filament\Resources\ReportsResource\Widgets\ReportFilters;
use Livewire\Attributes\On;

class ListReports extends ListRecords
{
    protected static string $resource = ReportsResource::class;

    public ?string $currentReportType = null;

    public array $reportFilters = [
        'date_from' => null,
        'date_to' => null,
        'departments' => [],
    ];

    public function mount(): void
    {
        parent::mount();

        $this->currentReportType = request()->query(
            'report',
            'work-activity'
        );

        $this->reportFilters = [
            'date_from' => now()
                ->subDays(120)
                ->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'departments' => [],
        ];
    }

    #[On('report-filters-applied')]
    public function applyReportFilters(array $filters): void
    {
        $this->reportFilters = [
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'departments' => array_values(
                array_map(
                    'intval',
                    $filters['departments'] ?? []
                ),
            ),
        ];
        $this->resetTable();
    }

    protected function getHeaderWidgets(): array
    {
        // Both report drivers read the applied shared filters back from
        // $this->reportFilters inside their getQuery(), so the header widget is
        // required for them.
        if (! in_array($this->currentReportType, ['sumar', 'work-activity'], true)) {
            return [];
        }

        return [
            ReportFilters::class,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubHeading(): string|Htmlable|null
    {
        $currentReport = $this->currentReportType;
        $reports = ReportFactory::getAvailable();

        // Get current driver and last synced date
        $driver = ReportFactory::make($currentReport);
        $lastSyncedAt = $driver->lastSyncedAt();

        $options = collect($reports)->mapWithKeys(fn ($driver, $key) => [
            $key => $driver->name(),
        ])->toArray();

        $onChange = <<<'JS'
            const value = event.target.value;
            window.location.href = window.location.pathname + '?report=' + value;
        JS;

        $optionsHtml = collect($options)->map(function ($label, $key) use ($currentReport) {
            $selected = $currentReport === $key ? 'selected' : '';

            return "<option value=\"{$key}\" {$selected}>{$label}</option>";
        })->join('');

        return new HtmlString(Blade::render('
        <div class="flex items-center justify-between gap-x-4">
            <div class="flex items-center gap-x-3">
                <label class="whitespace-nowrap text-sm font-medium text-gray-700 dark:text-gray-200">Zvoľte typ reportu:</label>
                <select onchange="{!! $onChange !!}" 
                    class="fi-select-input block w-full rounded-lg border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:ring-white/20">
                    {!! $optionsHtml !!}
                </select>
                <div class="flex flex-col text-sm text-gray-500 dark:text-gray-400">
                    <span class="whitespace-nowrap">Posledná synchronizácia:</span>
                    <strong class="whitespace-nowrap">{{ $lastSyncedAt }}</strong>
                </div>
            </div>

            <x-filament::button 
                wire:click="runExport" 
                icon="heroicon-m-arrow-down-tray"
                color="primary"
            >
                Exportovať Report
            </x-filament::button>
        </div>
    ', [
            'onChange' => $onChange,
            'optionsHtml' => $optionsHtml,
            'lastSyncedAt' => $lastSyncedAt,
        ]));
    }

    public function runExport(): void
    {
        $driver = ReportFactory::make($this->currentReportType);

        $filters = $this->getTableFiltersForm()->getState();

        $exporter = $driver->getExporter();
        $filename = $driver->generateExportFilename();

        $dateFrom = data_get($filters, 'date_range.date_from');
        $dateTo = data_get($filters, 'date_range.date_to');

        if ($dateFrom && $dateTo) {
            $validation = $this->validateDateRange($dateFrom, $dateTo);

            if (! $validation['isValid']) {
                Notification::make()
                    ->body($validation['error'])
                    ->danger()
                    ->send();

                return;
            }
        }

        ExportReportJob::dispatch(
            new $exporter,
            $filters,
            $filename,
            auth()->id(),
        );

        Notification::make()
            ->title(__('reports/export.export_started.title'))
            ->body(__('reports/export.export_started.body'))
            ->success()
            ->send();
    }

    private function validateDateRange($from, $to): array
    {
        $validator = new DateRangeValidator;

        return $validator->validate($from, $to);
    }
}

