<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';

    protected $fillable = [
        'order_id', 'line_id', 'start_date', 'finish_date', 'planned_finish_date',
        'target_qty', 'actual_qty', 'sequence_no', 'status',
    ];

    protected $attributes = [
        'actual_qty' => 0,
        'status'     => self::STATUS_PLANNED,
        'version'    => 1,
    ];

    protected $casts = [
        'start_date'          => 'date:Y-m-d',
        'finish_date'         => 'date:Y-m-d',
        'planned_finish_date' => 'date:Y-m-d',
        'target_qty'          => 'integer',
        'actual_qty'          => 'integer',
        'sequence_no'         => 'integer',
        'version'             => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleDay::class)->orderBy('work_date');
    }

    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('start_date', '<=', $to)->where('finish_date', '>=', $from);
    }

    /** True once any day has an actual value (even 0). Such a schedule is "locked". */
    public function hasActuals(): bool
    {
        return ScheduleDay::where('schedule_id', $this->id)->whereNotNull('actual_qty')->exists();
    }

    /** Days the schedule runs past its own planned finish (for the "+N hari" badge). */
    public function getDelayDaysAttribute(): int
    {
        return max(0, (int) $this->planned_finish_date->diffInDays($this->finish_date, false));
    }
}
