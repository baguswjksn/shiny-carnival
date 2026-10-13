<?php

namespace App\Services;

use App\Exceptions\ScheduleConflictException;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function list(): Collection
    {
        return Order::withSum('schedules as scheduled_qty', 'target_qty')->orderByDesc('id')->get();
    }

    public function create(array $data): Order
    {
        return $this->withTotals(Order::create($data)->refresh());
    }

    public function update(Order $order, array $data): Order
    {
        $scheduled = (int) $order->schedules()->sum('target_qty');
        if ((int) $data['total_qty'] < $scheduled) {
            throw ValidationException::withMessages([
                'total_qty' => "Qty order tidak boleh di bawah qty yang sudah dijadwalkan ({$scheduled}).",
            ]);
        }

        $order->update($data);

        return $this->withTotals($order);
    }

    public function delete(Order $order): void
    {
        if ($order->schedules()->exists()) {
            throw new ScheduleConflictException('Order masih dipakai schedule. Hapus schedule-nya dulu.');
        }

        $order->delete();
    }

    private function withTotals(Order $order): Order
    {
        $order->scheduled_qty = (int) $order->schedules()->sum('target_qty');

        return $order;
    }
}
