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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use App\Events\ViewerStream;
use App\Services\StreamViewerService;

class StreamController extends Controller
{
    public function mostrarTodasLasTransmisiones()
    {
        $transmisiones = Stream::with('canal:id,nombre,stream_key')->get();

        $transmisiones = $transmisiones->map(function ($transmision) {
                
                $pattern = "stream:{$transmision->id}:viewer:*";

                $viewers = count(Redis::keys($pattern));

                $transmision->viewers = $viewers;

                return $transmision;
            });

        $host = $this->obtenerHostMinio();
        $bucket = $this->obtenerBucket();
        $transmisiones->each(fn($t) => $this->procesarMiniaturaTransmision($t, $host, $bucket));

        return response()->json($transmisiones);
    }

    public function verTransmision($transmisionId)
    {
        $transmision = $this->obtenerTransmisionConRelaciones($transmisionId);
        $host        = $this->obtenerHostMinio();
        $bucket      = $this->obtenerBucket();
        $this->procesarMiniaturaTransmision($transmision, $host, $bucket);
        $this->procesarFotoUsuario($transmision, $host, $bucket);
        $urlHls = $this->generarUrlHls($transmision);
        return response()->json([
            'transmision' => $transmision,
            'url_hls'     => $urlHls,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function hlsEvent(Request $request)
    {
        $event = $request->input('call');       
        $name  = $request->input('name');       
        $addr  = $request->input('addr', $request->ip());

        if (!$name || !in_array($event, ['on_play', 'on_play_done'])) {
            return response('bad request', 400);
        }

        $streamKey = "live/{$name}";
        $redisKey  = "viewers:{$streamKey}";
        $setKey    = "viewers:set:{$streamKey}";

        if ($event === 'on_play') {
            $added = Redis::sadd($setKey, $addr);

            if ($added) {
                $current = Redis::incr($redisKey);

                if ($current == 1) {
                    Redis::setex("stream:active:{$name}", 3600, now()->toDateTimeString());
                }
            }

            Redis::expire($redisKey, 300);
            Redis::expire($setKey, 300);
        }

        if ($event === 'on_play_done') {
            $removed = Redis::srem($setKey, $addr);

            if ($removed) {
                $current = Redis::decr($redisKey);
            }

            if (($current ?? 0) <= 0) {
                Redis::del($redisKey, $setKey);
            }
        }

        return response()->json([
            'stream'   => $name,
            'viewers'  => max(0, Redis::get($redisKey) ?? 0),
            'event'    => $event,
            'ip'       => $addr,
        ]);
    }

    public function status($key)
    {
        $base = '/mnt/hls/' . $key;

        $playlist = $base . '/index.m3u8';

        if (!file_exists($playlist)) {
            return response()->json([
                'online' => false,
                'message' => 'Stream not found or offline'
            ]);
        }

        $content = file_get_contents($playlist);

        preg_match_all('/(\d+)\.ts/', $content, $matches);

        if (empty($matches[1])) {
            return response()->json([
                'online' => true,
                'bitrate' => null,
                'segments' => 0,
                'message' => 'Waiting for segments...'
            ]);
        }

        $lastSegment = max($matches[1]);
        $segmentPath = $base . '/' . $lastSegment . '.ts';

        $fileSizeBytes = file_exists($segmentPath) ? filesize($segmentPath) : 0;
        $duration = 3; 

        $bitrateKbps = $duration > 0
            ? round(($fileSizeBytes * 8 / 1024) / $duration)
            : null;

        return response()->json([
            'online' => true,
            'segments' => count($matches[1]),
            'current_segment' => $lastSegment,
            'bitrate_kbps' => $bitrateKbps,
            'playlist' => "/hls/{$key}/index.m3u8",
        ]);
    }


public function metricsHls($key)
{
    $base = "/mnt/hls/{$key}";
    $playlistPath = "{$base}/index.m3u8";

    if (!file_exists($playlistPath)) {
        return response()->json([
            'online' => false,
            'message' => 'Stream offline'
        ]);
    }

    $content = file_get_contents($playlistPath);

    preg_match_all('/(.*\.ts)/', $content, $matches);

    if (empty($matches[1])) {
        return response()->json([
            'online' => true,
            'segments' => 0,
            'message' => 'Waiting for segments...'
        ]);
    }

    $segments = $matches[1];
    $lastSegment = end($segments);
    $segmentPath = "{$base}/{$lastSegment}";

    $size = filesize($segmentPath);
    $durationSec = 3; 
    $bitrateKbps = round(($size * 8 / 1024) / $durationSec);

    $ffprobe = "ffprobe -v quiet -print_format json -show_streams \"$segmentPath\"";
    $metaJson = shell_exec($ffprobe);
    $meta = json_decode($metaJson, true);

    $videoStream = collect($meta['streams'])->firstWhere('codec_type', 'video');

    $width = $videoStream['width'] ?? null;
    $height = $videoStream['height'] ?? null;
    $fps = null;

    if (isset($videoStream['r_frame_rate'])) {
        list($num, $den) = explode('/', $videoStream['r_frame_rate']);
        $fps = round($num / $den);
    }

    $latencySeconds = count($segments) * $durationSec;

    $playlistAgeSeconds = time() - filemtime($playlistPath);

    return response()->json([
        'online'            => true,
        'segments'          => count($segments),
        'current_segment'   => $lastSegment,
        'bitrate_kbps'      => $bitrateKbps,
        'resolution'        => "{$width}x{$height}",
        'fps'               => $fps,
        'latency_seconds'   => $latencySeconds,
        'playlist_age'      => $playlistAgeSeconds,
        'playlist'          => "/hls/{$key}/index.m3u8",
    ]);
}


    public function obtenerViewers($stream_key)
    {
        $redisKey = "viewers:live/{$stream_key}";
        $viewers  = (int) Redis::get($redisKey) ?: 0;

        return response()->json(['viewers' => $viewers]);
    }

    public function heartbeat(Request $request, $streamId)
    {
        $userId = $request->query('user_id');

        $service = new StreamViewerService();
        $count = $service->heartbeat($streamId, $userId);

        broadcast(new ViewerStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count,
        ]));

        return response()->json(['ok' => true]);
    }

    private function obtenerTransmisionConRelaciones($transmisionId)
    {
        return Stream::with([
            'canal:id,nombre,user_id,stream_key',
            'canal.user:id,name,foto',
        ])->findOrFail($transmisionId);
    }

    private function procesarFotoUsuario($transmision, $host, $bucket)
    {
        if ($transmision->canal->user->foto) {
            $transmision->canal->user->foto = $this->obtenerUrlArchivo($transmision->canal->user->foto, $host, $bucket);
        }
    }

    private function generarUrlHls($transmision)
    {
        if (!$transmision->activo) {
            return null;
        }

        return sprintf(
            '%s%s/index.m3u8',
            rtrim(env('STREAM_BASE_LINK'), '/').'/',
            $transmision->canal->stream_key
        );
    }

    private function procesarMiniaturaTransmision($transmision, $host, $bucket)
    {
        if ($transmision->miniatura) {
            $transmision->miniatura = $this->obtenerUrlArchivo($transmision->miniatura, $host, $bucket);
        }
    }

    private function obtenerHostMinio()
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
    }

    private function obtenerBucket()
    {
        return env('AWS_BUCKET') . '/';
    }

    private function obtenerUrlArchivo($rutaRelativa, $host, $bucket)
    {
        if (! $rutaRelativa) {
            return null;
        }
        if (str_starts_with($rutaRelativa, $host . $bucket)) {
            return $rutaRelativa;
        }
        if (filter_var($rutaRelativa, FILTER_VALIDATE_URL)) {
            return $rutaRelativa;
        }
        return $host . $bucket . $rutaRelativa;
    }
    
    public function guardarNuevaTransmision(Request $request, $canal_id)
    {
        $canal = Canal::findOrFail($canal_id);
        $this->validarDatosTransmision($request);
        try {
            $transmision = $this->crearTransmision($request, $canal);
        } catch (QueryException $e) {
            return $this->manejarExcepcionTransmision($e, $canal);
        }
        if ($request->hasFile('miniatura')) {
            $this->guardarMiniatura($request, $transmision, $canal_id);
        }
        return response()->json([
            'message'     => 'Transmisión creada con éxito.',
            'transmision' => $transmision,
        ], 201);
    }

    private function validarDatosTransmision(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
        ]);
    }

    private function crearTransmision(Request $request, $canal)
    {
        return Stream::create([
            'titulo'      => $request->titulo,
            'descripcion' => $request->descripcion,
            'activo'      => false,
            'canal_id'    => $canal->id,
        ]);
    }

    private function manejarExcepcionTransmision(QueryException $e, $canal)
    {
        if ($e->getCode() === '23000') {
            return response()->json([
                'stream'      => true,
                'message'     => 'Ya existe una transmisión asociada a este canal.',
                'transmision' => $canal->fresh()->streams,
            ], 200);
        }
        throw $e;
    }

    private function guardarMiniatura(Request $request, $transmision, $canal_id)
    {
        $miniatura       = $request->file('miniatura');
        $nombreMiniatura = "{$transmision->id}.jpg";
        $folderPath      = "miniaturas-streams/{$canal_id}";
        $rutaMiniatura   = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');

        $transmision->miniatura = $rutaMiniatura;
        $transmision->save();
    }

    public function listarTransmisionOBS(Request $request, $canalId)
    {
        $userId = $request->input('user_id');
        if (! $userId) {
            return response()->json(['message' => 'El user_id es requerido.'], 400);
        }
        $canal = Canal::findOrFail($canalId);
        if ($canal->user_id !== (int) $userId) {
            return response()->json(['message' => 'No tienes permiso para acceder a este canal.'], 403);
        }
        return response()->json([
            'server'     => env('RTMP_SERVER'),
            'stream_key' => $canal->stream_key,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function actualizarDatosDeTransmision(Request $request, $transmisionId, $canalId)
    {
        $transmision = Stream::findOrFail($transmisionId);
        if ($transmision->canal_id !== (int) $canalId) {
            return response()->json(['message' => 'No tienes permiso para actualizar esta transmisión.'], 403);
        }
        $this->validarDatosActualizacion($request);
        $transmision->update($request->only(['titulo', 'descripcion']));

        if ($request->hasFile('miniatura')) {
            $this->actualizarMiniatura($request, $transmision, $canalId);
        }
        return response()->json([
            'message'     => 'Transmisión actualizada con éxito.',
            'transmision' => $transmision,
        ]);
    }

    private function validarDatosActualizacion(Request $request)
    {
        $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:255',
            'miniatura'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    }

    private function actualizarMiniatura(Request $request, $transmision, $canalId)
    {
        $miniatura       = $request->file('miniatura');
        $nombreMiniatura = "{$transmision->id}.jpg";
        $folderPath      = "miniaturas-streams/{$canalId}";
        $rutaMiniatura   = $miniatura->storeAs($folderPath, $nombreMiniatura, 's3');

        $transmision->miniatura = $rutaMiniatura;
        $transmision->save();
    }

    public function eliminarTransmision($canal_id)
    {
        $canal       = Canal::findOrFail($canal_id);
        $transmision = $canal->streams;
        if (! $transmision) {
            return response()->json(['message' => 'No se encontró ninguna transmisión asociada a este canal.'], 404);
        }
        $this->eliminarArchivoStream($transmision);
        $transmision->delete();
        return response()->json(['message' => 'Transmisión eliminada con éxito.']);
    }

    private function eliminarArchivoStream($transmision)
    {
        $archivo = $this->obtenerArchivoFLVDesdeMinio($transmision);
        if ($archivo) {
            $rutaArchivo = "streams/{$archivo}";
            if (Storage::disk('s3')->exists($rutaArchivo)) {
                Storage::disk('s3')->delete($rutaArchivo);
            }
        }
    }

    public function subirVideoDeStream($streamId, Request $request)
    {
        try {
            $stream        = $this->obtenerStreamPorId($streamId);
            $archivoFLV    = $this->obtenerArchivoFLVDesdeMinio($stream);
            $rutaMP4       = $this->convertirFLVaMP4($archivoFLV);
            $rutaS3        = $this->subirVideoConvertidoAMinio($stream, $rutaMP4, $archivoFLV);
            $rutaMiniatura = $this->moverMiniaturaDeStream($stream);
            $video         = $this->crearRegistroDeVideo($stream, $rutaS3, $rutaMiniatura, $rutaMP4);
            if ($request->has('etiquetas') && is_array($request->etiquetas)) {
                $this->asignarEtiquetasAlVideo($request, $video->id);
            }
            return response()->json([
                'mensaje'       => 'El video y la miniatura se subieron correctamente.',
                'video_url'     => $this->obtenerUrlArchivo($rutaS3, $this->obtenerHostMinio(), $this->obtenerBucket()),
                'miniatura_url' => $this->obtenerUrlArchivo($rutaMiniatura, $this->obtenerHostMinio(), $this->obtenerBucket()),
                'video_id'      => $video->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Error al subir el video.',
                'detalle' => $e->getMessage(),
            ], 500);
        }
    }

    private function obtenerStreamPorId($streamId)
    {
        $stream = Stream::find($streamId);
        if (! $stream) {
            throw new \Exception('No se encontró un stream con el ID proporcionado.');
        }
        return $stream;
    }

    private function obtenerArchivoFLVDesdeMinio($stream)
    {
        $directorio = 'streams';
        $streamKey  = $stream->canal->stream_key;
        $patron     = '/^' . preg_quote("{$directorio}/{$streamKey}-", '/') . '\d+\.flv$/';
        return collect(Storage::disk('s3')->files($directorio))
            ->first(fn($archivo) => preg_match($patron, $archivo)) ?? throw new \Exception('Archivo FLV no encontrado en MinIO.');
    }

    private function convertirFLVaMP4(string $rutaFLV): string
    {
        $nombreBase   = pathinfo($rutaFLV, PATHINFO_FILENAME);
        $rutaLocalFLV = $this->descargarArchivoDesdeMinio($rutaFLV, $nombreBase, 'flv');
        $rutaLocalMP4 = $this->convertirArchivoConFFMpeg($rutaLocalFLV, $nombreBase, 'mp4');
        $this->eliminarArchivoLocal($rutaLocalFLV);
        return $rutaLocalMP4;
    }

    private function descargarArchivoDesdeMinio(string $rutaRemota, string $nombreBase, string $extension): string
    {
        $rutaLocal = storage_path("app/temp/{$nombreBase}.{$extension}");
        $this->crearDirectorioSiNoExiste(dirname($rutaLocal));
        Storage::disk('s3')->getDriver()->getAdapter()->getClient()->getObject([
            'Bucket' => env('AWS_BUCKET'),
            'Key'    => $rutaRemota,
            'SaveAs' => $rutaLocal,
        ]);
        if (! file_exists($rutaLocal)) {
            throw new \Exception("Error al descargar el archivo desde MinIO: {$rutaRemota}");
        }
        return $rutaLocal;
    }

    private function convertirArchivoConFFMpeg(string $rutaEntrada, string $nombreBase, string $extensionSalida): string
    {
        $rutaSalida = storage_path("app/temp/{$nombreBase}.{$extensionSalida}");
        $comando    = [
            env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
            '-y',
            '-i', $rutaEntrada,
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-crf', '23',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $rutaSalida,
        ];
        $this->ejecutarComandoFFMpeg($comando);

        if (! file_exists($rutaSalida)) {
            throw new \Exception("Error al generar el archivo convertido: {$rutaSalida}");
        }
        return $rutaSalida;
    }

    private function ejecutarComandoFFMpeg(array $comando): void
    {
        $proceso = new Process($comando);

        try {
            $proceso->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('Error al ejecutar FFMpeg', ['error' => $e->getMessage(), 'output' => $proceso->getErrorOutput()]);
            throw new \Exception('Error al convertir el archivo con FFMpeg.');
        }
    }

    private function eliminarArchivoLocal(string $rutaArchivo): void
    {
        if (file_exists($rutaArchivo)) {
            unlink($rutaArchivo);
        }
    }

    private function subirVideoConvertidoAMinio($stream, string $rutaMP4, string $archivoFLV): string
    {
        $rutaS3 = $this->guardarArchivoEnS3($stream, $rutaMP4);
        $this->eliminarArchivoDeMinio($archivoFLV);
        return $rutaS3;
    }

    private function guardarArchivoEnS3($stream, string $rutaArchivo): string
    {
        $carpeta       = "videos/{$stream->canal_id}";
        $nombreArchivo = bin2hex(random_bytes(16)) . '.mp4';
        $rutaS3        = "{$carpeta}/{$nombreArchivo}";

        Storage::disk('s3')->put($rutaS3, file_get_contents($rutaArchivo), [
            'Metadata' => [
                'nombre_stream' => $stream->titulo,
                'descripcion'   => $stream->descripcion,
            ],
        ]);

        return $rutaS3;
    }

    private function eliminarArchivoDeMinio(string $rutaArchivo): void
    {
        if (Storage::disk('s3')->exists($rutaArchivo)) {
            Storage::disk('s3')->delete($rutaArchivo);
        }
    }

    private function moverMiniaturaDeStream($stream): string
    {
        $rutaAntigua = "miniaturas-streams/{$stream->canal_id}/{$stream->id}.jpg";
        $nuevaRuta   = "miniaturas/{$stream->canal_id}/" . uniqid() . '.jpg';
        if (! Storage::disk('s3')->exists($rutaAntigua)) {
            throw new \Exception('Miniatura no encontrada en la ubicación original.');
        }
        $contenido = Storage::disk('s3')->get($rutaAntigua);
        Storage::disk('s3')->put($nuevaRuta, $contenido);
        Storage::disk('s3')->delete($rutaAntigua);
        return $nuevaRuta;
    }

    private function crearRegistroDeVideo($stream, string $rutaVideo, string $rutaMiniatura, string $rutaMP4): Video
    {
        $duracion = $this->obtenerDuracionDeVideo($rutaMP4);
        $video    = Video::create([
            'titulo'      => $stream->titulo,
            'descripcion' => $stream->descripcion,
            'link'        => $rutaVideo,
            'miniatura'   => $rutaMiniatura,
            'duracion'    => $duracion,
            'bloqueado'   => false,
            'acceso'      => 'publico',
            'canal_id'    => $stream->canal_id,
        ]);
        $stream->delete();
        $this->eliminarArchivoLocal($rutaMP4);
        return $video;
    }

    private function obtenerDuracionDeVideo(string $rutaArchivo): int
    {
        if (! file_exists($rutaArchivo)) {
            throw new \Exception('Archivo no encontrado para calcular la duración.');
        }
        $ffprobe  = \FFMpeg\FFProbe::create();
        $duracion = $ffprobe->format($rutaArchivo)->get('duration');

        if ($duracion === null) {
            throw new \Exception('No se pudo obtener la duración del video.');
        }
        return (int) floor($duracion);
    }

    private function asignarEtiquetasAlVideo(Request $request, int $videoId): void
    {
        $etiquetasController = new EtiquetaController();
        $etiquetasController->asignarEtiquetas($request, $videoId);
    }

    private function crearDirectorioSiNoExiste(string $directorio): void
    {
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
    }

    public function descargarStream($streamId)
    {
        $stream       = Stream::findOrFail($streamId);
        $archivoFLV   = $this->obtenerArchivoFLVParaStream($stream);
        $rutaLocalFlv = $this->descargarArchivoDesdeMinio($archivoFLV, pathinfo($archivoFLV, PATHINFO_FILENAME), 'flv');
        $rutaLocalMp4 = $this->convertirFLVaMP4($rutaLocalFlv);
        $this->eliminarArchivoLocal($rutaLocalFlv);
        return response()->download($rutaLocalMp4)->deleteFileAfterSend(true);
    }

    private function obtenerArchivoFLVParaStream($stream)
    {
        $streamKey = $stream->canal->stream_key;
        $archivos  = Storage::disk('s3')->files('streams');
        return collect($archivos)
            ->filter(fn($ruta) => str_starts_with(basename($ruta), $streamKey) && str_ends_with($ruta, '.flv'))
            ->sortDesc()
            ->first() ?? throw new \Exception("No se encontró ningún archivo FLV para la stream key: {$streamKey}");
    }

    public function entrarView(Request $request, $streamId)
    {
        $service = new StreamViewerService();
        $count = $service->añadirViewer((int)$streamId);

        return response()->json([
            'ok' => true,
            'viewers' => $count
        ]);
    }

    public function salirView(Request $request, $streamId)
    {
        $service = new StreamViewerService();
        $count = $service->eliminarViewer((int)$streamId);


        return response()->json([
            'ok' => true,
            'viewers' => $count
        ]);
    }

    public function iniciarStream(Request $request)
    {
        return $this->gestionarEstadoStream($request, 'iniciar', true);
    }

    public function finalizarStream(Request $request)
    {
        return $this->gestionarEstadoStream($request, 'finalizar', false);
    }

    private function gestionarEstadoStream(Request $request, string $accion, bool $estado)
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
            ->where('activo', ! $estado)
            ->latest('created_at')
            ->first();
        if (! $transmision) {
            return response()->json([
                'error' => $estado
                ? 'No hay transmisiones disponibles para iniciar'
                : 'No hay transmisiones activas para finalizar',
            ], 400);
        }

        $transmision->update(['activo' => $estado]);

       if ($estado === true) {
        broadcast(new ViewerStream($transmision->id, [
            'type' => 'stream_started',
            'stream_id' => $transmision->id,
            'started_at' => now()->toISOString(),
        ]));
        } else {
        broadcast(new ViewerStream($transmision->id, [
            'type' => 'stream_finished',
            'stream_id' => $transmision->id,
            'ended_at' => now()->toISOString(),
        ]));
        }

        return response()->json([
            'message'        => $estado ? 'Stream iniciado' : 'Stream finalizado',
            'transmision_id' => $transmision->id,
            'activo'         => $transmision->activo,
        ], 200);
    }
}
