<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportesComentarioSeeder extends Seeder
{
    public function run()
    {
        $reportes = [];

        for ($i = 1; $i <= 10; $i++) {
            $reportes[] = [
                'user_id' => 2,
                'comentario_id' => 4,
                'detalle' => 'Este es un reporte de prueba ' . $i,
                'lenguaje_ofensivo' => ($i % 2 == 0),
                'spam' => ($i % 3 == 0),
                'contenido_enganoso' => ($i % 4 == 0),
                'incitacion_al_odio' => false,
                'acoso' => false,
                'contenido_sexual' => false,
                'otros' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('reporta_comentario')->insert($reportes);
    }
}