<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Canal;

class CanalSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();

        foreach ($users as $user) {
            Canal::create([
                'nombre' => 'Canal de ' . $user->name,
                'descripcion' => 'Descripción del canal de ' . $user->name,
                'user_id' => $user->id,
            ]);
        }
    }
}
