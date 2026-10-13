<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('base_target');                // initial even split
            $table->integer('carry_in')->default(0);               // negative = surplus from previous day
            $table->unsignedInteger('target_qty');                 // effective target
            $table->unsignedInteger('actual_qty')->nullable();     // null = not entered yet
            $table->boolean('is_extension')->default(false);       // day added by balancing

            $table->unique(['schedule_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
    }
};
