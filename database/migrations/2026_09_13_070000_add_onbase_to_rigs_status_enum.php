<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUSES_WITH_ONBASE = "'drilling','developing','fishing','dtm','casing','stopped','onbase'";
    private const STATUSES_WITHOUT_ONBASE = "'drilling','developing','fishing','dtm','casing','stopped'";

    public function up(): void
    {
        if ($this->usesMySqlEnum()) {
            DB::statement("ALTER TABLE rigs MODIFY status ENUM(" . self::STATUSES_WITH_ONBASE . ") NOT NULL DEFAULT 'drilling'");
        }
    }

    public function down(): void
    {
        if ($this->usesMySqlEnum()) {
            DB::table('rigs')->where('status', 'onbase')->update(['status' => 'stopped']);
            DB::statement("ALTER TABLE rigs MODIFY status ENUM(" . self::STATUSES_WITHOUT_ONBASE . ") NOT NULL DEFAULT 'drilling'");
        }
    }

    private function usesMySqlEnum(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
