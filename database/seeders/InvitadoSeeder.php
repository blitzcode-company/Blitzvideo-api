<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InvitadoSeeder extends Seeder
{
    public function run()
    {
        $csvFile = base_path('database/csv/invitado.csv');
        $csv = array_map('str_getcsv', file($csvFile));
        foreach ($csv as $row) {
            $user = User::firstOrCreate([
                'name' => $row[0],
                'email' => $row[1],
            ], [
                'password' => Hash::make($row[2]),
            ]);
        }
    }
}
