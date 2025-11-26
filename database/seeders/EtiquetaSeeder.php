<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Etiqueta;
use Illuminate\Support\Facades\DB;

class EtiquetaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        
        Etiqueta::truncate(); 

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $etiquetas = [
            'Publicidad', 
            'Belleza', 
            'Entretenimiento', 
            'Educación', 
            'Moda', 
            'Videojuegos', 
            'Vlogs', 
            'Estilo De Vida', 
            'Cocina', 
            'Gastronomía', 
            'Viajes', 
            'Aventuras', 
            'Música', 
            'Tecnología', 
            'Deporte', 
            'Fitness', 
            'Salud y Bienestar', 
            'Noticias', 
            'Animación', 
            'Ciencia', 
            'Historia', 
            'Hogar y Jardín', 
            'Finanzas Personales', 
            'Negocios', 
            'Autos y Motos', 
            'Documentales', 
            'Cine', 
            'Series', 
            'Peliculas', 
            'Tráiler',
            'Stream',
            'Live',
            'Upcoming',
        ];

        foreach ($etiquetas as $nombre) {
            Etiqueta::create(['nombre' => $nombre]); 
        }
    }
}
