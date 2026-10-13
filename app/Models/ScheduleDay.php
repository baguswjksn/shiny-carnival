<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDay extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'schedule_id', 'work_date', 'base_target', 'carry_in',
        'target_qty', 'actual_qty', 'is_extension',
    ];

    protected $casts = [
        'work_date'    => 'date:Y-m-d',
        'base_target'  => 'integer',
        'carry_in'     => 'integer',
        'target_qty'   => 'integer',
        'actual_qty'   => 'integer',
        'is_extension' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
