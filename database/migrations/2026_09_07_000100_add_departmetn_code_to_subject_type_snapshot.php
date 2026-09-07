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
        });
    }

    public function down(): void
    {
        Schema::table('mvw_work_task_subject_snapshots', function (Blueprint $table) {
            $table->dropColumn('department_code');
        });
    }
};
