<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('rig_id')
                ->nullable()
                ->after('position_id')
                ->constrained('rigs')
                ->nullOnDelete();

            $table->index('rig_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['rig_id']);
            $table->dropColumn('rig_id');
        });
    }
};
