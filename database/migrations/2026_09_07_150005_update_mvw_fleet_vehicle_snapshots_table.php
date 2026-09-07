<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mvw_fleet_vehicle_snapshots', function (Blueprint $table) {
            $table->string('vin', 255)->nullable()->after('id');
            $table->string('maintenance_group_code', 255)->nullable()->after('type');
            $table->string('maintenance_group_title', 255)->nullable()->after('maintenance_group_code');
            $table->decimal('length', 8, 2)->nullable()->after('maintenance_group_title');
            $table->integer('seats')->nullable()->after('length');

            $table->unsignedBigInteger('maintenance_group_id')->nullable()->after('seats');
            $table->unsignedBigInteger('model_id')->nullable()->after('maintenance_group_id');
            $table->unsignedBigInteger('type_id')->nullable()->after('model_id');
        });
    }

    public function down(): void
    {
        Schema::table('mvw_fleet_vehicle_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'vin',
                'maintenance_group_code',
                'maintenance_group_title',
                'length',
                'seats',

                'maintenance_group_id',
                'model_id',
                'type_id',
            ]);
        });
    }
};
