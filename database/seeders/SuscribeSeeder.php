<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuscribeSeeder extends Seeder
{
    public function run()
    {
        for ($userId = 2; $userId <= 8; $userId++) {
            for ($canalId = 2; $canalId <= 8; $canalId++) {
                DB::table('suscribe')->insert([
                    'user_id' => $userId,
                    'canal_id' => $canalId,
                ]);
            }
        }
    }
}
