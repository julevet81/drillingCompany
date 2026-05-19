<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('photo')->nullable();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('position_id');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('periode', ['day', 'night']);
            $table->foreignId('rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->timestamps();

            $table->index(['date', 'periode']);
            $table->index('rig_id');
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
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
    }
};
