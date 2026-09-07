<?php

namespace App\Reports\Drivers;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

interface ReportDriver
{
    /**
     * Get the unique key for this driver (used in query parameters and tabs)
     */
    public function key(): string;

    /**
     * Get the human-readable name for this driver
     */
    public function name(): string;

    /**
     * Get the icon for this driver (heroicon names)
     */
    public function getQuery(): Builder;

    /**
     * Get the columns configuration for the table
     */
    public function getColumns(): array;

    /**
     * Get the filters configuration for the table
     */
    public function getFilters(): array;

    public function getSortColumn(): string;

    public function getSortOrder(): string;

    public function getExporter(): string;

    public function generateExportFilename(): string;

    /**
     * Apply additional query modifications if needed (for relationships, eager loading, etc.)
     */
    public function applyQueryModifications(Builder $query): Builder;

    public function lastSyncedAt(): ?string;
}
