<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_report_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('daily_reports')->cascadeOnDelete();
            $table->foreignId('drilling_tool_id')->constrained('drilling_tools')->restrictOnDelete();
            $table->integer('quantity_used')->default(0);
            $table->decimal('total_length', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_report_tools');
    }
};
