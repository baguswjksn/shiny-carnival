<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('line_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('finish_date');
            $table->date('planned_finish_date');
            $table->unsignedInteger('target_qty');
            $table->unsignedInteger('actual_qty')->default(0);   // cache of SUM(schedule_days.actual_qty)
            $table->unsignedInteger('sequence_no')->default(1);
            $table->enum('status', ['planned', 'running', 'done'])->default('planned');
            $table->unsignedInteger('version')->default(1);       // optimistic locking
            $table->timestamps();

            $table->index(['line_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
