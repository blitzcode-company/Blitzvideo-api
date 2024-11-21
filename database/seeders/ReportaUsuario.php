<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;


class ReportaUsuario extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $reportes = [];

        for ($i = 1; $i <= 10; $i++) {
            $reportes[] = [
                'id_reportante' => 2,
                'id_reportado' => 4,
                'detalle' => 'Este es un reporte de prueba ' . $i,
                'ciberacoso' => ($i % 2 == 0),
                'privacidad' => ($i % 3 == 0),
                'suplantacion_identidad' => ($i % 4 == 0),
                'amenazas' => false,
                'incitacion_odio' => false,
                'otros' => false,
                'estado' => 'pendiente',  
                'revisado_en' => null, 
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('reporta_usuario')->insert($reportes);
    }
}
