<?php
namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Stream;
use App\Models\Video;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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

        $url_hls = $transmision->activo
        ? env('STREAM_BASE_LINK') . "{$transmision->canal->stream_key}.m3u8"
        : null;

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
        try {
            $transmision = Stream::create([
                'titulo'      => $request->titulo,
                'descripcion' => $request->descripcion,
                'activo'      => false,
                'canal_id'    => $canal->id,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'stream'      => true,
                    'message'     => 'Ya existe una transmisión asociada a este canal.',
                    'transmision' => $canal->fresh()->streams,
                ], 200);
            }
            throw $e;
        }
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

    public function ListarServerYStreamKey(Request $request, $canalId)
    {
        $user_id = $request->input('user_id');

        if (! $user_id) {
            return response()->json(['message' => 'El user_id es requerido.'], 400);
        }

        $canal = Canal::where('id', (int) $canalId)->firstOrFail();

        if ($canal->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para acceder a este canal.'], 403);
        }

        $response = [
            'server'     => env('RTMP_SERVER'),
            'stream_key' => $canal->stream_key,
        ];

        return response()->json($response, 200, [], JSON_UNESCAPED_SLASHES);
    }


    public function ListarTransmisionOBS(Request $request, $canalId, $transmisionId)
    {
        $user_id = $request->input('user_id');
    
        if (! $user_id) {
            return response()->json(['message' => 'El user_id es requerido.'], 400);
        }
    
        $canal = Canal::where('id', (int) $canalId)->firstOrFail();
    
        if ($canal->user_id !== (int) $user_id) {
            return response()->json(['message' => 'No tienes permiso para acceder a este canal.'], 403);
        }
    
        $transmision = Stream::where('id', $transmisionId)
            ->where('canal_id', $canalId)
            ->firstOrFail();
    
            $url_hls = $transmision->activo
            ? env('STREAM_BASE_LINK') . "{$transmision->canal->stream_key}.m3u8"
            : null;
            
        return response()->json([
            'transmision' => $transmision,
            'url_hls' => $url_hls
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

    public function eliminarTransmision($canal_id)
    {
        $canal       = Canal::findOrFail($canal_id);
        $transmision = $canal->streams;

        if (! $transmision) {
            return response()->json(['message' => 'No se encontró ninguna transmisión asociada a este canal.'], 404);
        }

        $archivo = $this->ObtenerArchivoEnMinio($transmision);
        if ($archivo) {
            $rutaArchivo = "streams" . $archivo;
            if ($rutaArchivo && file_exists($rutaArchivo)) {
                @unlink($rutaArchivo);
            }
        }

        $transmision->delete();

        return response()->json([
            'message' => 'Transmisión eliminada con éxito.',
        ]);
    }

    public function subirVideoDeStream($streamId, Request $request)
    {
        try {
            $stream            = $this->obtenerStream($streamId);
            $archivoFLVMinio   = $this->ObtenerArchivoEnMinio($stream);
            $rutaConvertidaMP4 = $this->convertirVideoAFormatoMP4($archivoFLVMinio);
            $rutaS3            = $this->subirArchivoAMinIO($stream, $rutaConvertidaMP4, $archivoFLVMinio);
            $rutaMiniatura     = $this->procesarMiniatura($stream);
            $urlVideo          = Storage::disk('s3')->url($rutaS3);
            $video             = $this->crearVideo($stream, $urlVideo, $rutaMiniatura, $rutaConvertidaMP4);
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
                'error'   => 'Error al subir el video.',
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

    private function ObtenerArchivoEnMinio($stream)
    {
        $directorioMinio = 'streams';
        $streamKey       = $stream->canal->stream_key;
        $patron          = '/^' . preg_quote("{$directorioMinio}/{$streamKey}-", '/') . '\d+\.flv$/';
        $archivo         = collect(Storage::disk('s3')->files($directorioMinio))
            ->first(fn($archivo) => preg_match($patron, $archivo));
        return $archivo ?: null;
    }

    private function convertirVideoAFormatoMP4(string $rutaMinioFlv): string
    {
        $nombreBase   = pathinfo($rutaMinioFlv, PATHINFO_FILENAME);
        $rutaLocalFlv = $this->descargarArchivoFLVDesdeMinIO($rutaMinioFlv, $nombreBase);
        $rutaLocalMp4 = $this->convertirFLVaMP4($rutaLocalFlv, $nombreBase);
        $this->eliminarArchivoTemporal($rutaLocalFlv);
        return $rutaLocalMp4;
    }

    private function descargarArchivoFLVDesdeMinIO(string $rutaMinioFlv, string $nombreBase): string
    {
        $rutaLocalFlv = storage_path("app/temp/{$nombreBase}.flv");
        $directorio   = dirname($rutaLocalFlv);

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        if (! Storage::disk('s3')->exists($rutaMinioFlv)) {
            throw new \Exception("El archivo FLV no existe en MinIO: {$rutaMinioFlv}");
        }

        Storage::disk('s3')->getDriver()->getAdapter()->getClient()->getObject([
            'Bucket' => env('AWS_BUCKET'),
            'Key'    => $rutaMinioFlv,
            'SaveAs' => $rutaLocalFlv,
        ]);

        if (! file_exists($rutaLocalFlv)) {
            throw new \Exception("Error al guardar el archivo FLV localmente.");
        }

        return $rutaLocalFlv;
    }

    private function convertirFLVaMP4(string $rutaLocalFlv, string $nombreBase): string
    {
        $rutaLocalMp4 = storage_path("app/temp/{$nombreBase}.mp4");
        $ffmpegPath   = env('FFMPEG_BINARIES', '/usr/bin/ffmpeg');
        $command      = [
            $ffmpegPath,
            '-y',
            '-i', $rutaLocalFlv,
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-crf', '23',
            '-c:a', 'aac',
            '-strict', '-2',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $rutaLocalMp4,
        ];

        $this->ejecutarProcesoFFMpeg($command);

        if (! file_exists($rutaLocalMp4)) {
            throw new \Exception("No se generó el archivo MP4 en: {$rutaLocalMp4}");
        }
        return $rutaLocalMp4;
    }

    private function ejecutarProcesoFFMpeg(array $command): void
    {
        $process = new Process($command);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            Log::error("Error al convertir el video con FFMpeg", [
                'error'  => $exception->getMessage(),
                'output' => $process->getErrorOutput(),
            ]);
            throw new \Exception("Error al convertir el video: " . $exception->getMessage());
        }
    }

    private function eliminarArchivoTemporal(string $rutaArchivo): void
    {
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }
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

    private function subirArchivoAMinIO($stream, $rutaArchivo, $archivoFLVMinio)
    {
        $rutaS3 = $this->guardarArchivoEnS3($stream, $rutaArchivo);
        $this->eliminarArchivoFLVMinio($archivoFLVMinio);

        return $rutaS3;
    }

    private function guardarArchivoEnS3($stream, $rutaArchivo)
    {
        $carpetaCanal  = "videos/" . $stream->canal_id;
        $nombreArchivo = bin2hex(random_bytes(16)) . '.mp4';
        $rutaS3        = $carpetaCanal . "/" . $nombreArchivo;

        Storage::disk('s3')->put($rutaS3, file_get_contents($rutaArchivo), [
            'Metadata' => [
                'nombre_stream' => $stream->titulo,
                'descripcion'   => $stream->descripcion,
            ],
        ]);

        return $rutaS3;
    }

    private function eliminarArchivoFLVMinio($archivoFLVMinio)
    {
        if (Storage::disk('s3')->exists($archivoFLVMinio)) {
            Storage::disk('s3')->delete($archivoFLVMinio);
        }
    }

    private function crearVideo($stream, $urlVideo, $rutaMiniatura, $rutaArchivoMP4temp)
    {

        $duracion     = $this->obtenerDuracionDeVideo($rutaArchivoMP4temp);
        $urlVideo     = str_replace('minio', 'localhost', $urlVideo);
        $urlminiatura = Storage::disk('s3')->url($rutaMiniatura);
        $urlminiatura = str_replace('minio', 'localhost', $urlminiatura);
        $video        = Video::create([
            'titulo'      => $stream->titulo,
            'descripcion' => $stream->descripcion,
            'link'        => $urlVideo,
            'miniatura'   => $urlminiatura,
            'duracion'    => $duracion,
            'bloqueado'   => false,
            'acceso'      => 'publico',
            'canal_id'    => $stream->canal_id,
        ]);
        if ($video) {
            $stream->delete();
        } else {
        }
        if (file_exists($rutaArchivoMP4temp)) {
            unlink($rutaArchivoMP4temp);
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
        $stream = Stream::findOrFail($streamId);
        $streamKey = $stream->canal->stream_key;
        $archivos = Storage::disk('s3')->files('streams');
        $archivoFLV = collect($archivos)
            ->filter(fn($ruta) => str_starts_with(basename($ruta), $streamKey) && str_ends_with($ruta, '.flv'))
            ->sortDesc()
            ->first();
    
        if (!$archivoFLV) {
            throw new \Exception("No se encontró ningún archivo FLV para la stream key: {$streamKey}");
        }
        $nombreBase = pathinfo($archivoFLV, PATHINFO_FILENAME);
        $rutaLocalFlv = $this->descargarArchivoFLVDesdeMinIO($archivoFLV, $nombreBase);
        $rutaLocalMp4 = $this->convertirFLVaMP4($rutaLocalFlv, $nombreBase);
        if (file_exists($rutaLocalFlv)) {
            unlink($rutaLocalFlv);
        }
        return response()->download($rutaLocalMp4)->deleteFileAfterSend(true);
    }
    
    public function IniciarStream(Request $request)
    {

        $stream_key = $request->input('name');
        if (! $stream_key) {
            return response()->json(['error' => 'Stream key no proporcionado'], 400);
        }
        $canal = Canal::where('stream_key', $stream_key)->first();
        if (! $canal) {
            return response()->json(['error' => 'Canal no encontrado'], 404);
        }
        $transmision = Stream::where('canal_id', $canal->id)
            ->where('activo', false)
            ->latest('created_at')
            ->first();

        if (! $transmision) {
            return response()->json(['error' => 'No hay transmisiones disponibles para iniciar'], 400);
        }
        $transmision->update(['activo' => true]);
        return response()->json([
            'message'        => 'Stream iniciado',
            'transmision_id' => $transmision->id,
            'activo'         => $transmision->activo,
        ], 200);
    }

    public function FinalizarStream(Request $request)
    {
        $stream_key = $request->input('name');
        $canal      = Canal::where('stream_key', $stream_key)->first();

        if (! $canal) {
            return response()->json(['error' => 'Canal no encontrado'], 404);
        }

        $transmision = Stream::where('canal_id', $canal->id)
            ->where('activo', true)
            ->latest('created_at')
            ->first();

        if (! $transmision) {
            return response()->json(['error' => 'No hay transmisiones activas para finalizar'], 400);
        }

        $transmision->update(['activo' => false]);

        return response()->json(['message' => 'Stream finalizado'], 200);
    }
}
