<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            // References which rig the equipment is currently at
            $table->foreignId('current_rig_id')->nullable()->constrained('rigs')->nullOnDelete();
            $table->string('name');
            $table->string('marque')->nullable();          // Brand: Caterpillar, Volvo…
            $table->string('serial_number')->unique()->nullable();
            $table->string('photo')->nullable();
            $table->decimal('hours_of_operation')->nullable();
            $table->enum('status', ['operational', 'maintenance', 'out_of_service'])->default('operational');
            $table->timestamps();
            $table->softDeletes();

            $table->index('current_rig_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
