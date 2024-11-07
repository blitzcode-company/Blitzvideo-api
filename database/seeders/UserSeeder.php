<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run()
    {

        $faker = Faker::create();

        $names = ['Diego', 'Kevin', 'Mateo', 'Sophia', 'William', 'Olivia', 'James', 'Ava', 'Alexander', 'Isabella'];

        foreach ($names as $name) {
            $email = strtolower($name) . '@gmail.com';

            User::create([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'fecha_de_nacimiento' => $faker->dateTimeBetween('-50 years', '-18 years')->format('Y-m-d'), 
                'premium' => rand(0, 1) ? true : false,
                'bloqueado' => false,
                'foto' => null,
                'remember_token' => null,
            ]);
        }
    }
}