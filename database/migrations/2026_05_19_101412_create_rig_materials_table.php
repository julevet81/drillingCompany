<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master list of material types: Diesel Fuel, Bentonite, Barite, Cement…
        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit'); // L, kg, T, m³…
        });

        // Stock per rig per material type
        Schema::create('rig_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rig_id')->constrained('rigs')->cascadeOnDelete();
            $table->foreignId('material_type_id')->constrained('material_types')->restrictOnDelete();
            $table->decimal('quantity', 10, 2)->default(0);  // current stock
            $table->decimal('capacity', 10, 2)->nullable();   // max capacity

            $table->unique(['rig_id', 'material_type_id']);
            $table->index('rig_id');
        });

        // Daily consumption / refill log linked to a daily report
        Schema::create('material_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('rig_material_id')->constrained('rig_materials')->cascadeOnDelete();
            $table->date('log_date');
            $table->decimal('consumed', 10, 2)->default(0);
            $table->decimal('added', 10, 2)->default(0);
            $table->decimal('remaining', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['rig_material_id', 'log_date']);
            $table->index('report_id');
        });

        Schema::create('daily_report_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->restrictOnDelete();
            $table->enum('status', ['Operational', 'Maintenance', 'Out_of_Service'])->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'equipment_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_equipment');
        Schema::dropIfExists('material_logs');
        Schema::dropIfExists('rig_materials');
        Schema::dropIfExists('material_types');
    }
};
