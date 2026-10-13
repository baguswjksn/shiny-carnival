<?php

namespace Database\Seeders;

use App\Models\Line;
use Illuminate\Database\Seeder;

class LineSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1, 2, 3, 4] as $n) {
            Line::firstOrCreate(
                ['code' => sprintf('LINE-%02d', $n)],
                ['name' => "Sewing Line $n", 'daily_capacity' => null, 'is_active' => true]
            );
        }
    }
}
