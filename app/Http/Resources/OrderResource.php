<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduled = (int) ($this->scheduled_qty ?? 0);

        return [
            'id'            => $this->id,
            'order_no'      => $this->order_no,
            'buyer'         => $this->buyer,
            'style'         => $this->style,
            'total_qty'     => $this->total_qty,
            'due_date'      => $this->due_date?->toDateString(),
            'status'        => $this->status,
            'scheduled_qty' => $scheduled,
            'remaining_qty' => max($this->total_qty - $scheduled, 0),
        ];
    }
}
