<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stream;
use App\Models\Canal;

class StreamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $canales = Canal::take(5)->get();

        if ($canales->isEmpty()) {
            $this->command->error('No se encontraron canales suficientes en la base de datos.');
            return;
        }

        foreach ($canales as $canal) {
            Stream::create([
                'titulo' => 'Transmisión del canal ' . $canal->nombre,
                'descripcion' => 'Esta es una transmisión en vivo del canal ' . $canal->nombre,
                'stream_key' => uniqid('stream_'),
                'activo' => false,
                'canal_id' => $canal->id,
            ]);
        }
    }
}
