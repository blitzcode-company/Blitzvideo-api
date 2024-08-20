<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportesSeeder extends Seeder
{
    public function run()
    {
        $reportes = [];

        for ($i = 1; $i <= 10; $i++) {
            $reportes[] = [
                'user_id' => 2,
                'video_id' => 3,
                'detalle' => 'Este es un reporte de prueba ' . $i,
                'contenido_inapropiado' => ($i % 2 == 0),
                'spam' => ($i % 3 == 0),
                'contenido_enganoso' => ($i % 4 == 0),
                'violacion_derechos_autor' => false,
                'incitacion_al_odio' => false,
                'violencia_grafica' => false,
                'otros' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('reporta')->insert($reportes);
    }
}
