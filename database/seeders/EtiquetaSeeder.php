<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Etiqueta;

class EtiquetaSeeder extends Seeder
{
    public function run()
    {
        $etiquetas = [
            'Belleza', 'Entretenimiento', 'Educación', 'Moda', 'Videojuegos',
            'Vlogs', 'Estilo De Vida', 'Cocina', 'Gastronomía', 'Viajes',
            'Aventuras', 'Música', 'Tecnología', 'Deporte', 'Fitness'
        ];

        foreach ($etiquetas as $nombre) {
            Etiqueta::create(['nombre' => $nombre]);
        }
    }
}