<?php
namespace Database\Seeders;

use App\Models\Canal;
use App\Models\Etiqueta;
use App\Models\Stream;
use App\Models\Video;
use Illuminate\Database\Seeder;

class StreamSeeder extends Seeder
{

    public function run()
    {
        $canales = Canal::take(5)->get();

        if ($canales->isEmpty()) {
            $this->command->error('No se encontraron canales suficientes en la base de datos.');
            return;
        }

        $etiquetasStream = Etiqueta::whereIn('nombre', ['Stream', 'Live', 'Upcoming'])
            ->pluck('id', 'nombre')
            ->toArray();

        if (! isset($etiquetasStream['Stream']) || ! isset($etiquetasStream['Live']) || ! isset($etiquetasStream['Upcoming'])) {
            $this->command->error('Faltan las etiquetas "Stream", "Live" o "Upcoming". Asegúrate de ejecutar EtiquetaSeeder primero.');
            return;
        }

        foreach ($canales as $canal) {
            $streamTitle       = 'Transmisión del canal ' . $canal->nombre;
            $streamDescription = 'Esta es una transmisión en vivo del canal ' . $canal->nombre;

            $video = Video::create([
                'titulo'      => $streamTitle,
                'descripcion' => $streamDescription,
                'link'        => 'http://ejemplo.com/stream-link-' . $canal->id,
                'canal_id'    => $canal->id,
                'miniatura'   => 'default_stream_miniatura.png',
                'duracion'    => 0,
                'bloqueado'   => false,
                'acceso'      => 'publico',
            ]);

            $activo = (bool) rand(0, 1);

            $stream = Stream::create([
                'video_id'          => $video->id,
                'stream_programado' => now()->addMinutes(rand(10, 60)),
                'max_viewers'       => rand(50, 500),
                'total_viewers'     => rand(500, 5000),
                'activo'            => $activo,
            ]);

            $tagsToAttach = [$etiquetasStream['Stream']];

            if ($activo) {
                $tagsToAttach[] = $etiquetasStream['Live'];
            } else {
                $tagsToAttach[] = $etiquetasStream['Upcoming'];
            }

            $video->etiquetas()->attach($tagsToAttach);

            $stream->canales()->attach($canal->id);
        }
    }
}
