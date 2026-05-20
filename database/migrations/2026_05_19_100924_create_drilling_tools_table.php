<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Outil, Raccord, Masse Tige 1-4, Tige, Kelly
            $table->timestamps();
        });

        Schema::create('drilling_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tool_type_id')->constrained('tool_types')->restrictOnDelete();
            $table->string('name')->nullable();
            $table->string('external_diameter')->nullable();
            $table->decimal('unit_length', 10, 2)->nullable();
            $table->integer('total_quantity')->default(0);
            $table->string('status')->nullable();
            $table->foreignId('rig_id')->constrained('rigs')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['rig_id', 'tool_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drilling_tools');
        Schema::dropIfExists('tool_types');
    }
};
