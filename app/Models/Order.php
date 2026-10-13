<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN      = 'open';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_DONE      = 'done';

    protected $fillable = ['order_no', 'buyer', 'style', 'total_qty', 'due_date', 'status'];

    protected $casts = [
        'total_qty' => 'integer',
        'due_date'  => 'date:Y-m-d',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
