<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Original requested start. start_date can be pushed later by other schedules on the
            // line, and is pulled back toward this date when the line frees up.
            $table->date('planned_start_date')->nullable()->after('start_date');
        });

        DB::table('schedules')->update(['planned_start_date' => DB::raw('start_date')]);
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('planned_start_date');
        });
    }
};
