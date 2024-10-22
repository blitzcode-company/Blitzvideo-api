<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            InvitadoSeeder::class,
            UserSeeder::class,
            CanalSeeder::class,
            EtiquetaSeeder::class,
            VideoSeeder::class,
            ReportesSeeder::class,
            ComentarioSeeder::class,
            ReportesComentarioSeeder::class,
            SuscribeSeeder::class,
            TransaccionSeeder::class,
            PublicidadSeeder::class,
            VisitaSeeder::class
        ]);
    }
}
