<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;


class InvitadoSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create(); 
        $csvFile = base_path('database/csv/invitado.csv');
        $csv = array_map('str_getcsv', file($csvFile));
        foreach ($csv as $row) {
            $user = User::firstOrCreate([
                'name' => $row[0],
                'email' => $row[1],
            ], [
                'password' => Hash::make($row[2]),
                'fecha_de_nacimiento' => $faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'),
            ]);
        }
    }
}
