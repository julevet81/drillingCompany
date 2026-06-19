<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rig_id')->constrained('rigs')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->decimal('depth_start', 10, 2)->default(0);
            $table->decimal('depth_end', 10, 2)->default(0);
            $table->decimal('daily_progress', 10, 2)->default(0);

            $table->decimal('fuel_consumption', 12, 2)->default(0);

            // NPT / Safety fields (visible in Rig Detail page)
            $table->integer('incidents')->default(0);
            $table->decimal('npt_hours', 12, 2)->default(0);  // Non-Productive Time
            $table->string('npt_cause')->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rig_id', 'report_date']);
            $table->index(['rig_id', 'report_date']);
            $table->index('report_date');
            $table->index('created_by');
        });

        
    }

    public function down(): void
    {
        
        Schema::dropIfExists('daily_reports');
    }
};
