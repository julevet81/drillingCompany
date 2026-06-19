<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->enum('post', ['post_1', 'post_2']);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->index('report_id');
        });

        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('function')->nullable();
            $table->enum('status', ['onsite', 'onBase', 'onLeave'])->default('onsite');
            $table->primary(['shift_id', 'employee_id']);
            $table->index('status');
        });
        

    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shifts');

    }
};
