<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إضافة report_id لجدول shifts
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('report_id')
                ->nullable()
                ->after('id')
                ->constrained('daily_reports')
                ->cascadeOnDelete();

            $table->dropForeign(['rig_id']);
            $table->dropIndex('shifts_rig_id_index');
            $table->dropColumn('rig_id');

            $table->unique(['report_id', 'periode']); // تقرير واحد → shift واحد نهار + shift واحد ليل
        });

        // 2. حذف جدول daily_report_employees
        Schema::dropIfExists('daily_report_employees');

        // 3. حذف workers_count من daily_reports
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropColumn('workers_count');
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->integer('workers_count')->default(0)->after('daily_progress');
        });

        Schema::create('daily_report_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->boolean('present')->default(true);
            $table->timestamps();
            $table->unique(['report_id', 'shift_id']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropUnique(['report_id', 'periode']);
            $table->dropForeign(['report_id']);
            $table->dropColumn('report_id');

            $table->foreignId('rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->index('rig_id');
        });
    }
};
