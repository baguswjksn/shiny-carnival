<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            ['PO-2610-001', 'Buyer A', 'Polo Shirt', 1200],
            ['PO-2610-002', 'Buyer A', 'Chino Pants', 800],
            ['PO-2610-003', 'Buyer B', 'Hoodie', 1500],
            ['PO-2610-004', 'Buyer C', 'Jacket', 600],
            ['PO-2610-005', 'Buyer B', 'T-Shirt', 2000],
        ];

        foreach ($orders as [$no, $buyer, $style, $qty]) {
            Order::firstOrCreate(
                ['order_no' => $no],
                ['buyer' => $buyer, 'style' => $style, 'total_qty' => $qty]
            );
        }
    }
}
