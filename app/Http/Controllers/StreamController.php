<?php
namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Stream;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StreamController extends Controller
{
    public function mostrarTodasLasTransmisiones()
    {
        $transmisiones = Stream::with('canal:id,nombre')->get();
        return response()->json($transmisiones);
    }

    public function verTransmision($transmisionId)
    {
        $transmision = Stream::with([
            'canal'      => function ($query) {
                $query->select('id', 'nombre', 'user_id', 'stream_key');
            },
            'canal.user' => function ($query) {
                $query->select('id', 'name', 'foto');
            },
        ])->findOrFail($transmisionId);
        $url_hls = env('STREAM_BASE_LINK') . "{$transmision->canal->stream_key}.m3u8";
        $transmision->setHidden(['stream_key']);
        return response()->json([
            'transmision' => $transmision,
            'url_hls'     => $url_hls,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function guardarNuevaTransmision(Request $request, $canal_id)
    {
        $canal = Canal::findOrFail($canal_id);

        $request->validate([
            'titulo' => 'required|string|max:255',
        ]);
        $transmision = Stream::create([
            'titulo'      => $request->titulo,
            'descripcion' => $request->descripcion,
            'canal_id'    => $canal->id,
        ]);
        if ($request->hasFile('miniatura')) {
            $miniatura              = $request->file('miniatura');
            $nombreMiniatura        = "{$transmision->id}.jpg";
            $folderPath             = "miniaturas-streams/{$canal_id}";
            $rutaMiniatura          = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');
            $miniaturaUrl           = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaMiniatura));
            $transmision->miniatura = $miniaturaUrl;
            $transmision->save();
        }

        return response()->json([
            'message'     => 'Transmisión creada con éxito.',
            'transmision' => $transmision,
        ], 201);
    }

    public function ListarTransmisionOBS($transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para acceder a esta transmisión.'], 403);
        }

        $transmision['server'] = env('RTMP_SERVER');

        return response()->json([
            'transmision' => $transmision,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function actualizarDatosDeTransmision(Request $request, $transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para actualizar esta transmisión.'], 403);
        }

        $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'miniatura'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $transmision->update($request->only(['titulo', 'descripcion']));
        if ($request->hasFile('miniatura')) {
            $miniatura              = $request->file('miniatura');
            $nombreMiniatura        = "{$transmision->id}.jpg";
            $folderPath             = "miniaturas-streams/{$canal_id}";
            $rutaMiniatura          = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');
            $miniaturaUrl           = str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($rutaMiniatura));
            $transmision->miniatura = $miniaturaUrl;
            $transmision->save();
        }

        return response()->json([
            'message'     => 'Transmisión actualizada con éxito.',
            'transmision' => $transmision,
        ]);
    }

    public function eliminarTransmision($transmisionId, $canal_id)
    {
        $transmision = Stream::findOrFail($transmisionId);

        if ($transmision->canal_id !== (int) $canal_id) {
            return response()->json(['message' => 'No tienes permiso para eliminar esta transmisión.'], 403);
        }

        $archivoCorrespondiente = $this->obtenerArchivoCorrespondiente($transmision);
        if ($archivoCorrespondiente) {
            $rutaArchivo = $this->obtenerRutaArchivo($archivoCorrespondiente);
            if (file_exists($rutaArchivo)) {
                unlink($rutaArchivo);
            }
            $transmision->delete();
            return response()->json([
                'message' => 'Transmisión y video eliminados con éxito.',
            ]);
        }

        $transmision->delete();
        return response()->json([
            'message' => 'Transmisión eliminada con éxito.',
        ]);
    }

    public function subirVideoDeStream($streamId, Request $request)
    {
        try {
            $stream                 = $this->obtenerStream($streamId);
            $archivoCorrespondiente = $this->obtenerArchivoCorrespondiente($stream);
            $rutaArchivo            = $this->obtenerRutaArchivo($archivoCorrespondiente);
            $rutaMiniatura          = $this->procesarMiniatura($stream);
            $rutaS3                 = $this->subirArchivoAMinIO($stream, $rutaArchivo);
            $urlVideo               = Storage::disk('s3')->url($rutaS3);
            $video                  = $this->crearVideo($stream, $urlVideo, $rutaMiniatura, $rutaArchivo);
            if ($request->has('etiquetas') && is_array($request->etiquetas)) {
                $this->asignarEtiquetas($request, $video->id);
            }
            return response()->json([
                'mensaje'       => 'El video y la miniatura se subieron correctamente.',
                'video_url'     => $urlVideo,
                'miniatura_url' => Storage::disk('s3')->url($rutaMiniatura),
                'video_id'      => $video->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Error al subir el video a MinIO.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }

    private function obtenerStream($streamId)
    {
        $stream = Stream::where('id', $streamId)->first();
        if (! $stream) {
            throw new \Exception('No se encontró un stream con esa clave.');
        }
        return $stream;
    }

    private function obtenerArchivoCorrespondiente($stream)
    {
        $carpetaPersistencia = "/app/streams/records";
        $archivo             = collect(scandir($carpetaPersistencia))
            ->first(fn($archivo) => str_starts_with($archivo, $stream->canal->stream_key));
        if (! $archivo) {
            throw new \Exception('No se encontró ningún archivo correspondiente al nombre del stream.');
        }
        return $archivo;
    }

    private function obtenerRutaArchivo($archivoCorrespondiente)
    {
        $carpetaPersistencia = "/app/streams/records";

        return $carpetaPersistencia . DIRECTORY_SEPARATOR . $archivoCorrespondiente;
    }

    private function procesarMiniatura($stream)
    {
        $rutaMiniaturaAntigua = "miniaturas-streams/{$stream->canal_id}/{$stream->id}.jpg";
        $nuevoNombreMiniatura = "miniaturas/{$stream->canal_id}/" . uniqid() . '.jpg';
        if (Storage::disk('s3')->exists($rutaMiniaturaAntigua)) {
            $contenidoMiniatura = Storage::disk('s3')->get($rutaMiniaturaAntigua);
            Storage::disk('s3')->put($nuevoNombreMiniatura, $contenidoMiniatura);
            Storage::disk('s3')->delete($rutaMiniaturaAntigua);
        } else {
            throw new \Exception("No se encontró la miniatura en la ubicación original.");
        }

        return $nuevoNombreMiniatura;
    }

    private function subirArchivoAMinIO($stream, $rutaArchivo)
    {
        $carpetaCanal  = "videos/" . $stream->canal_id;
        $nombreArchivo = bin2hex(random_bytes(16)) . '.flv';
        $rutaS3        = $carpetaCanal . "/" . $nombreArchivo;

        Storage::disk('s3')->put($rutaS3, file_get_contents($rutaArchivo), [
            'Metadata' => [
                'nombre_stream' => $stream->titulo,
                'descripcion'   => $stream->descripcion,
            ],
        ]);

        return $rutaS3;
    }

    private function crearVideo($stream, $urlVideo, $rutaMiniatura, $rutaArchivo)
    {

        $duracion = $this->obtenerDuracionDeVideo($rutaArchivo);

        $video = Video::create([
            'titulo'      => $stream->titulo,
            'descripcion' => $stream->descripcion,
            'link'        => $urlVideo,
            'miniatura'   => Storage::disk('s3')->url($rutaMiniatura),
            'duracion'    => $duracion,
            'bloqueado'   => false,
            'acceso'      => 'publico',
            'canal_id'    => $stream->canal_id,
        ]);
        if ($video) {
            $stream->delete();
        } else {
        }

        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }

        return $video;
    }

    private function obtenerDuracionDeVideo($rutaArchivo)
    {
        try {

            if (! file_exists($rutaArchivo)) {
                throw new \Exception("Archivo no encontrado.");
            }

            $ffprobe               = \FFMpeg\FFProbe::create();
            $duracionTotalDelVideo = $ffprobe
                ->format($rutaArchivo)
                ->get('duration');

            if ($duracionTotalDelVideo !== null) {
                return (int) floor($duracionTotalDelVideo);
            }

            throw new \Exception("No se pudo obtener la duración del video.");
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function asignarEtiquetas($request, $videoId)
    {
        $etiquetasController = new EtiquetaController();
        $etiquetasController->asignarEtiquetas($request, $videoId);
    }

    public function descargarStream($streamId)
    {
        try {
            $stream              = Stream::findOrFail($streamId);
            $carpetaPersistencia = "/app/streams/records";
            $archivo             = collect(scandir($carpetaPersistencia))
                ->first(fn($archivo) => str_starts_with($archivo, $stream->canal->stream_key));

            if (! $archivo) {
                return response()->json(['error' => 'Archivo del stream no encontrado en la ubicación temporal.'], 404);
            }
            $rutaArchivo = $carpetaPersistencia . DIRECTORY_SEPARATOR . $archivo;
            if (! file_exists($rutaArchivo)) {
                return response()->json(['error' => 'El archivo del stream no está disponible.'], 404);
            }
            return response()->download($rutaArchivo, $stream->titulo . ".flv", [
                'Content-Type' => 'video/x-flv',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Error al intentar descargar el archivo del stream.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }

}
