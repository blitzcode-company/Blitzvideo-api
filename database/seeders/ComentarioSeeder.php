<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comentario;

class ComentarioSeeder extends Seeder
{
    public function run()
    {
        for ($i = 1; $i <= 10; $i++) {
            Comentario::create([
                'usuario_id' => 2,
                'video_id' => 3,
                'mensaje' => 'Este es el comentario número ' . $i,
                'respuesta_id' => null,
            ]);
        }
    }
}
