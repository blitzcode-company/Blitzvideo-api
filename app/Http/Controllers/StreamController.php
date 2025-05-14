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
        $host          = $this->obtenerHostMinio();
        $bucket        = $this->obtenerBucket();
        $transmisiones->each(fn($transmision) => $this->procesarMiniaturaTransmision($transmision, $host, $bucket));
        return response()->json($transmisiones, 200);
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
        return $transmision->activo
        ? sprintf('%s%s.m3u8', env('STREAM_BASE_LINK'), $transmision->canal->stream_key)
        : null;
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
        return $rutaRelativa ? $host . $bucket . $rutaRelativa : null;
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
        $archivo = $this->ObtenerArchivoEnMinio($transmision);
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

        return response()->json([
            'message'        => $estado ? 'Stream iniciado' : 'Stream finalizado',
            'transmision_id' => $transmision->id,
            'activo'         => $transmision->activo,
        ], 200);
    }
}
