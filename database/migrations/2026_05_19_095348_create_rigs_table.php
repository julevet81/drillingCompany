<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rigs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique()->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            // Status: active=Drilling/Devloping, paused=Stopped, completed, fishing, dtm, casing
            $table->enum('status', [
                'drilling',
                'developing',
                'fishing',
                'dtm',
                'casing',
                'stopped',
            ])->default('drilling');

            $table->decimal('current_depth', 10, 2)->default(0);
            $table->decimal('target_depth', 10, 2)->nullable();

            // e.g. "Drilling 8½\"", "Casing 13⅝\"", "Production", "Completion"
            $table->string('drilling_phase')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('manager_id');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rigs');
    }
};
