<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TerminalAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('terminal_accounts')->insertOrIgnore([
            'name'         => 'main',
            'balance'      => 0,
            'total_earned' => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}