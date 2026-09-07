<?php

use Dpb\Departments\Livewire\DepartmentSwitcherComponent;
use Dpb\DpbEmployeeManager\Filament\Pages\EmployeeManagerPage\EmployeeManagerPage;
use Dpb\DpbEmployeeManager\Models\Contract;
use Dpb\DpbEmployeeManager\Models\Group;
use Dpb\Insights\Filament\Pages\InsightsPage;
use Dpb\Spotlight\Components\Livewire\SpotlightTriggerComponent;
use Dpb\Spotlight\Models\SpotlightStep;
use Dpb\WorkTimeFund\Filament\Pages\DeferredTasksManagement\DeferredTasksManagementPage;
// use Dpb\WorkTimeFund\Filament\Pages\OperationsCategoryManagementPage;
use Dpb\WorkTimeFund\Models\Absence;
use Dpb\WorkTimeFund\Models\ActivityRecord;
use Dpb\WorkTimeFund\Models\Category;
use Dpb\WorkTimeFund\Models\Operation;
use Dpb\WorkTimeFund\Models\WorkTime;
use Dpb\WorkTimeFundFilament\Filament\Pages\AbsencesManagerPage;
use Dpb\WorkTimeFundFilament\Filament\Pages\OfficialTasksManagementPage;
use Dpb\WorkTimeFundFilament\Filament\Pages\OperationsManagementPage;
use Dpb\WorkTimeFundFilament\Filament\Pages\WorktimeManagementPage;
use Dpb\WtfTmsBridge\Filament\Pages\DailyMaintenanceWorkOrdersPage;
use Dpb\WtfTmsBridge\Filament\Pages\WorkOrderPage;

return [
    'enabled' => false,
    'rbac' => [
        'web' => [
            'veduci-pracovnik' => [
                // Pages
                InsightsPage::getAccessPermission(),
                OperationsManagementPage::getAccessPermission(),
                // OperationsCategoryManagementPage::getAccessPermission(),
                WorktimeManagementPage::getAccessPermission(),
                EmployeeManagerPage::getAccessPermission(),
                DeferredTasksManagementPage::getAccessPermission(),
                WorkOrderPage::getAccessPermission(),
                DailyMaintenanceWorkOrdersPage::getAccessPermission(),
                // Components
                DepartmentSwitcherComponent::getAccessPermission(),
                SpotlightTriggerComponent::getAccessPermission(),
                'dpb-departments.department.read_assigned',
                // Models
                ...WorkTime::getTablePermissions(),
                ...ActivityRecord::getTablePermissions(),
                ...Operation::getTablePermissions(),
                ...Category::getTablePermissions(),
                ...Group::getTablePermissions(),
                ...Contract::getTablePermissions(),
                // Specific permissions for custom tasks manager
                'dpb-wtf.custom_operations.read_category',

                'dpb-wtf.custom_operations.create_root_category',
                'dpb-wtf.custom_operations.create_category',
                'dpb-wtf.custom_operations.create_operation',
                'dpb-wtf.custom_operations.update_category',
                'dpb-wtf.custom_operations.update_operation',
                'dpb-wtf.custom_operations.duplicate_category',
                'dpb-wtf.custom_operations.duplicate_operation',
                'dpb-wtf.custom_operations.delete_category',
                'dpb-wtf.custom_operations.delete_operation',
                'dpb-wtf.custom_operations.restore_category',
                'dpb-wtf.custom_operations.restore_operation',
                'dpb-wtf.custom_operations.manage_access',
                'dpb-wtf.custom_operations.import',
                'dpb-wtf.custom_operations.export',
            ],
            'katalogizovane-ulohy' => [
                'dpb-departments.department.read_all',
                OfficialTasksManagementPage::getAccessPermission(),
                InsightsPage::getAccessPermission(),
                ...Operation::getTablePermissions(),
                ...Category::getTablePermissions(),
                // Specific permissions for official tasks manager
                'dpb-wtf.official_operations.read_category',

                'dpb-wtf.official_operations.create_root_category',
                'dpb-wtf.official_operations.create_category',
                'dpb-wtf.official_operations.create_operation',
                'dpb-wtf.official_operations.update_category',
                'dpb-wtf.official_operations.update_operation',
                'dpb-wtf.official_operations.duplicate_category',
                'dpb-wtf.official_operations.duplicate_operation',
                'dpb-wtf.official_operations.delete_category',
                'dpb-wtf.official_operations.delete_operation',
                'dpb-wtf.official_operations.restore_category',
                'dpb-wtf.official_operations.restore_operation',
                'dpb-wtf.official_operations.manage_access',
                'dpb-wtf.official_operations.import',
                'dpb-wtf.official_operations.export',
            ],
            'absence-manager' => [
                AbsencesManagerPage::getAccessPermission(),
                ...Absence::getTablePermissions()
            ],
            'spotlight-manager' => [
                SpotlightTriggerComponent::getAccessPermission(),
                ...SpotlightStep::getTablePermissions(),
            ]
        ],
    ],
];
