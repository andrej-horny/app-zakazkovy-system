<?php

namespace App\Filament\Resources\ReportsResource\Widgets;

use App\Services\DateRangeValidator;
use Dpb\DatahubSync\Models\Department;
use Dpb\Departments\Services\DepartmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\HtmlString;

class ReportFilters extends Widget implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.resources.reports.widgets.report-filters';

    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->subDays(120)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
            'departments' => [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        $validator = new DateRangeValidator(120);

        return $form
            ->schema([
                DatePicker::make('date_from')
                    ->label('Dátum od')
                    ->default(now()->subDays(120))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->format('Y-m-d'),

                DatePicker::make('date_to')
                    ->label('Dátum do')
                    ->default(now())
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->format('Y-m-d'),

                Select::make('departments')
                    ->label(__('reports/detail-report.table.filters.department'))
                    ->options(
                        fn (DepartmentService $departmentService) => Department::query()
                            ->whereIn(
                                'id',
                                $departmentService
                                    ->getAvailableDepartments()
                                    ->pluck('id')
                            )
                            ->pluck('code', 'id')
                            ->toArray()
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('Všetky strediská'),

                Placeholder::make('date_error')
                    ->hiddenLabel()
                    ->content(function ($get) use ($validator) {
                        $from = $get('date_from');
                        $to = $get('date_to');

                        if (! $from || ! $to) {
                            return '';
                        }

                        $validation = $validator->validate($from, $to);

                        if (! $validation['isValid']) {
                            return new HtmlString(
                                '<span style="color: red">'
                                .e($validation['error']).
                                '</span>'
                            );
                        }

                        return '';
                    })
                    ->columnSpanFull(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function apply(): void
    {
        $data = $this->form->getState();

        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;

        if ($dateFrom && $dateTo) {
            $validation = (new DateRangeValidator(120))
                ->validate($dateFrom, $dateTo);

            if (! $validation['isValid']) {
                Notification::make()
                    ->body($validation['error'])
                    ->danger()
                    ->send();

                return;
            }
        }

        $this->dispatch(
            'report-filters-applied',
            filters: [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'departments' => array_values(
                    array_map(
                        'intval',
                        $data['departments'] ?? []
                    )
                ),
            ],
        );
    }
}