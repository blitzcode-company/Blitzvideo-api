<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stream;
use App\Models\User;

class StreamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $usuarios = User::take(5)->get();
        if ($usuarios->isEmpty()) {
            $this->command->error('No se encontraron usuarios suficientes en la base de datos.');
            return;
        }
        foreach ($usuarios as $usuario) {
            Stream::create([
                'titulo' => 'Transmisión de ' . $usuario->name,
                'descripcion' => 'Esta es una transmisión en vivo realizada por ' . $usuario->name,
                'stream_key' => uniqid('stream_'),
                'activo' => true,
                'user_id' => $usuario->id,
            ]);
        }

        $this->command->info('Se han creado transmisiones para los primeros 5 usuarios.');
    }
}
