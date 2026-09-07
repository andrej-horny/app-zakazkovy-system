<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mvw_work_task_subject_snapshots', function (Blueprint $table) {
            $table->string('department_code')
                ->nullable()
                ->after('subject_label');
            $table->index(['department_code','subject_type'], 'mvw_work_task_subject_snapshots_idx_dep_code_sub_type');
        });
    }

    public function down(): void
    {
        Schema::table('mvw_work_task_subject_snapshots', function (Blueprint $table) {
            $table->dropColumn('department_code');
            $table->dropIndex('mvw_work_task_subject_snapshots_idx_dep_code_sub_type');
        });
    }
};
