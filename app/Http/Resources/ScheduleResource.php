<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $days = $this->relationLoaded('days') ? $this->days : collect();
        $planned = $days->where('is_extension', false)->values();

        return [
            'id'                  => $this->id,
            'order'               => $this->whenLoaded('order', fn () => [
                'id'        => $this->order->id,
                'order_no'  => $this->order->order_no,
                'buyer'     => $this->order->buyer,
                'style'     => $this->order->style,
                'total_qty' => $this->order->total_qty,
            ]),
            'line'                => $this->whenLoaded('line', fn () => [
                'id'   => $this->line->id,
                'code' => $this->line->code,
                'name' => $this->line->name,
            ]),
            'start_date'          => $this->start_date->toDateString(),
            'finish_date'         => $this->finish_date->toDateString(),
            'planned_finish_date' => $this->planned_finish_date->toDateString(),
            'delay_days'          => $this->delay_days,
            'target_qty'          => $this->target_qty,
            'actual_qty'          => $this->actual_qty,
            'balance_qty'         => max($this->target_qty - $this->actual_qty, 0),
            'target_per_day'      => $planned->first()?->base_target ?? 0,
            'last_day_target'     => $planned->last()?->base_target ?? 0,
            'status'              => $this->status,
            'sequence_no'         => $this->sequence_no,
            'version'             => $this->version,
            'locked'              => $days->contains(fn ($d) => $d->actual_qty !== null),
            'days'                => ScheduleDayResource::collection($this->whenLoaded('days')),
        ];
    }
}
