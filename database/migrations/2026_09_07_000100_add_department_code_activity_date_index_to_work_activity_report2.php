<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mvw_work_activity_report_v2', function (Blueprint $table) {

            $table->index(
                ['department_code', 'activity_date'],
                'mvw_work_activity_report_v2_dept_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mvw_work_activity_report_v2', function (Blueprint $table) {
            $table->dropIndex('mvw_work_activity_report_v2_dept_date');
        });
    }
};
