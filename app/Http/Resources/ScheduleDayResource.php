<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'work_date'    => $this->work_date->toDateString(),
            'base_target'  => $this->base_target,
            'carry_in'     => $this->carry_in,
            'target_qty'   => $this->target_qty,
            'actual_qty'   => $this->actual_qty,
            'is_extension' => $this->is_extension,
            'variance'     => $this->actual_qty === null ? null : $this->actual_qty - $this->target_qty,
        ];
    }
}
