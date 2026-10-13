<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'adminapp@sewing-scheduler.com'],
	    ['name' => 'adminapp', 'password' => Hash::make('m134y4mb4ks0!'),'role' => 'admin']
	);

        User::updateOrCreate(
            ['email' => 'viewapp@sewing-scheduler.com'],
	    ['name' => 'viewapp', 'password' => Hash::make('t4huw4l1k!'),'role' => 'viewer']
        );

    }
}
