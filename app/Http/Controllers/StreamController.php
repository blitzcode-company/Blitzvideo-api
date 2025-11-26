<?php
namespace App\Http\Controllers;

use App\Events\ViewerStream;
use App\Models\Canal;
use App\Models\Stream;
use App\Models\Video;
use App\Services\StreamViewerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class StreamController extends Controller
{

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

 private function obtenerUrlFotoPerfil(?string $rutaFoto, string $host, string $bucket)
    {
        if (! $rutaFoto) {
            return $this->obtenerUrlArchivo('users/default_profile.png', $host, $bucket); 
        }
        return $this->obtenerUrlArchivo($rutaFoto, $host, $bucket);
    }

    public function mostrarTodasLasTransmisiones()
    {
        $transmisiones = Stream::with([
            'video' => function ($query) {
                $query->select('id', 'titulo', 'descripcion', 'link', 'miniatura', 'duracion', 'canal_id');
                $query->with(['canal' => function ($canalQuery) {
                    $canalQuery->select('id', 'nombre', 'user_id'); 
                    $canalQuery->with('user:id,foto');                            
                }]);
            },
        ])->get();

        $host   = $this->obtenerHostMinio();
        $bucket = $this->obtenerBucket();

        $transmisiones = $transmisiones->map(function ($transmision) use ($host, $bucket) {

            if (! $transmision->video) {
                return null;
            }

            $pattern              = "stream:{$transmision->id}:viewer:*";
            $viewers              = count(Redis::keys($pattern));
            $transmision->viewers = $viewers;
            if ($transmision->video->miniatura) {
                $transmision->video->miniatura = $this->obtenerUrlArchivo($transmision->video->miniatura, $host, $bucket);
            }
            $foto_url = null;
            if (optional($transmision->video->canal)->user) {
                $foto_ruta = $transmision->video->canal->user->foto;
                $foto_url = $this->obtenerUrlFotoPerfil($foto_ruta, $host, $bucket); 
            }
            $canal_data = [
                'id'         => optional($transmision->video->canal)->id,
                'nombre'     => optional($transmision->video->canal)->nombre,
                'user_id'    => optional(optional($transmision->video->canal)->user)->id,
                'foto'       => $foto_url,
            ];

            return [
                'id'                => $transmision->id,
                'stream_programado' => $transmision->stream_programado,
                'max_viewers'       => $transmision->max_viewers,
                'total_viewers'     => $transmision->total_viewers,
                'activo'            => $transmision->activo,
                'viewers'           => $transmision->viewers,
                'video_id'          => $transmision->video->id,
                'titulo'            => $transmision->video->titulo,
                'descripcion'       => $transmision->video->descripcion,
                'link'              => $transmision->video->link,
                'miniatura'         => $transmision->video->miniatura,
                'duracion'          => $transmision->video->duracion,

                'canal'             => $canal_data,
            ];
        })->filter()->values();

        return response()->json($transmisiones);
    }

    public function verTransmision($transmisionId)
    {
        $transmision = $this->obtenerTransmisionConRelaciones($transmisionId);

        if (! $transmision || ! $transmision->video) {
            return response()->json(['message' => 'Transmisión o video asociado no encontrado.'], 404);
        }
        $host   = $this->obtenerHostMinio();
        $bucket = $this->obtenerBucket();
        if ($transmision->video->miniatura) {
            $transmision->video->miniatura = $this->obtenerUrlArchivo($transmision->video->miniatura, $host, $bucket);
        }
        if ($transmision->video->canal && $transmision->video->canal->user) {
            $this->procesarFotoUsuario($transmision, $host, $bucket);
        }
        $urlHls    = $this->generarUrlHls($transmision);
        $respuesta = [
            'id'                => $transmision->id,
            'stream_programado' => $transmision->stream_programado,
            'max_viewers'       => $transmision->max_viewers,
            'total_viewers'     => $transmision->total_viewers,
            'activo'            => $transmision->activo,
            'url_hls'           => $urlHls,
            'video'             => [
                'id'          => $transmision->video->id,
                'titulo'      => $transmision->video->titulo,
                'descripcion' => $transmision->video->descripcion,
                'link'        => $transmision->video->link,
                'miniatura'   => $transmision->video->miniatura,
                'duracion'    => $transmision->video->duracion,
                'etiquetas'   => $transmision->video->etiquetas,
                'created_at'   => $transmision->video->created_at,
            ],
            'canal'             => [
                'id'         => $transmision->video->canal->id,
                'nombre'     => $transmision->video->canal->nombre,
                'stream_key' => $transmision->video->canal->stream_key,
                'user'       => $transmision->video->canal->user,
            ],
        ];
        return response()->json($respuesta, 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function obtenerTransmisionConRelaciones($transmisionId)
    {
        return Stream::with([
            'video' => function ($query) {
                $query->with([
                    'canal' => function ($canalQuery) {
                        $canalQuery->with('user:id,name,foto');
                    },
                    'etiquetas:id,nombre',
                ]);
            },
        ])->findOrFail($transmisionId);
    }

    private function procesarFotoUsuario($transmision, $host, $bucket)
    {
        $usuario = $transmision->video->canal->user;
        if ($usuario && $usuario->foto) {
            $usuario->foto = $this->obtenerUrlArchivo($usuario->foto, $host, $bucket);
        }
    }

    private function generarUrlHls($transmision)
    {
        if (! $transmision->activo || ! $transmision->video || ! $transmision->video->canal) {
            return null;
        }
        return sprintf(
            '%s%s/index.m3u8',
            rtrim(env('STREAM_BASE_LINK'), '/') . '/',
            $transmision->video->canal->stream_key
        );
    }

    private function procesarDescripcion(string $texto): string
    {
        $texto = $this->linkify($texto);
        $texto = Purify::clean($texto);
        return $texto;
    }

    private function linkify(string $text): string
    {
        $pattern = '/\b(https?:\/\/[^\s<]+[^\s<.,;:!?")\]])/';
        return preg_replace_callback($pattern, function ($matches) {
            $url     = $matches[0];
            $display = htmlspecialchars(substr($url, 0, 60)) . (strlen($url) > 60 ? '...' : '');
            return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $display . '</a>';
        }, $text);
    }

    private function manejarExcepcionTransmision(QueryException $e, $canal)
    {
        if ($e->getCode() === '23000') {
            $streamExistente = $canal->streams()->where('activo', true)->first();

            return response()->json([
                'stream'      => true,
                'message'     => 'Ya existe una transmisión activa asociada a este canal. Finalízala antes de crear una nueva.',
                'transmision' => $streamExistente,
            ], 409);
        }
        throw $e;
    }

    public function guardarNuevaTransmision(Request $request, $canal_id)
    {
        $canal = Canal::findOrFail($canal_id);
        $this->validarDatosTransmision($request);

        $video = $this->crearVideoParaStream($request, $canal);

        if ($request->has('etiquetas')) {
            $this->asignarEtiquetas($request, $video->id);
        }

        try {
            $stream = Stream::create([
                'video_id'          => $video->id,
                'activo'            => false,
                'stream_programado' => $request->input('stream_programado'),
            ]);

            $canal->streams()->attach($stream->id);

            if ($request->hasFile('miniatura')) {
                $this->guardarMiniaturaStream($request, $stream, $video);
            }

        } catch (QueryException $e) {
            $video->delete();
            return $this->manejarExcepcionTransmision($e, $canal);
        }

        $stream->load(['video.etiquetas', 'video.canal']);

        return response()->json([
            'message'     => 'Transmisión creada con éxito.',
            'transmision' => $stream,
        ], 201);
    }

    private function validarDatosTransmision(Request $request)
    {
        $request->validate([
            'titulo'            => 'required|string|max:255',
            'descripcion'       => 'required|string',
            'etiquetas'         => 'nullable|array',
            'miniatura'         => 'nullable|file|image|max:10240',
            'stream_programado' => 'nullable|date',
        ]);
    }

    private function crearVideoParaStream(Request $request, Canal $canal): Video
    {
        $descripcion = $this->procesarDescripcion($request->descripcion);

        $video = new Video([
            'titulo'      => $request->titulo,
            'descripcion' => $descripcion,
            'link'        => 'stream_' . Str::random(50),
            'miniatura'   => 'miniatura_' . Str::random(50),
            'duracion'    => 0,
            'canal_id'    => $canal->id,
            'acceso'      => 'publico',
            'estado'      => 'PROGRAMADO',
        ]);
        $video->save();

        return $video;
    }

    private function guardarMiniaturaStream(Request $request, Stream $stream, Video $video)
    {
        if (! $request->hasFile('miniatura')) {
            return;
        }

        $miniatura       = $request->file('miniatura');
        $folderPath      = "miniaturas/{$video->canal_id}";
        $miniaturaNombre = uniqid() . '.jpg';

        $rutaMiniatura = $miniatura->storeAs($folderPath, $miniaturaNombre, 's3');

        $video->miniatura = $rutaMiniatura;
        $video->save();
    }

    private function asignarEtiquetas(Request $request, $videoId)
    {
        $video             = Video::findOrFail($videoId);
        $etiquetasIdsInput = $request->input('etiquetas', []);

        $etiquetaIds = collect($etiquetasIdsInput)
            ->filter(fn($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->toArray();

        $video->etiquetas()->sync($etiquetaIds);
    }

    public function actualizarDatosDeTransmision(Request $request, $transmisionId, $canalId)
    {
        $canal  = Canal::findOrFail($canalId);
        $stream = Stream::with('video')->findOrFail($transmisionId);

        $isAssociated = $canal->streams()->where('streams.id', $stream->id)->exists();

        if (! $isAssociated) {
            return response()->json(['message' => 'La transmisión no está asociada a este canal o no tienes permiso.'], 403);
        }

        $this->validarDatosActualizacion($request);

        $video = $stream->video;

        $descripcionProcesada = $this->procesarDescripcion($request->descripcion);

        $video->update([
            'titulo'      => $request->titulo,
            'descripcion' => $descripcionProcesada,
        ]);

        $stream->update($request->only('stream_programado'));

        if ($request->hasFile('miniatura')) {
            $this->actualizarMiniatura($request, $stream, $video);
        }

        if ($request->has('etiquetas')) {
            $this->asignarEtiquetas($request, $video->id);
        }

        $stream->load(['video.etiquetas', 'video.canal']);

        return response()->json([
            'message'     => 'Metadatos de la Transmisión (Video) actualizados con éxito.',
            'transmision' => $stream,
        ]);
    }

    private function validarDatosActualizacion(Request $request)
    {
        $request->validate([
            'titulo'            => 'required|string|max:255',
            'descripcion'       => 'required|string',
            'etiquetas'         => 'nullable|array',
            'miniatura'         => 'nullable|file|image|max:10240',
            'stream_programado' => 'nullable|date',
        ]);
    }

    private function actualizarMiniatura(Request $request, Stream $stream, Video $video)
    {
        if (! $request->hasFile('miniatura')) {
            return;
        }
        $miniatura = $request->file('miniatura');
        if ($video->miniatura) {
            $miniaturaNombre = basename($video->miniatura);
        } else {
            $miniaturaNombre = uniqid() . '.jpg';
        }
        $folderPath       = "miniaturas/{$video->canal_id}";
        $rutaMiniatura    = $miniatura->storeAs($folderPath, $miniaturaNombre, 's3');
        $video->miniatura = $rutaMiniatura;
        $video->save();
    }

    public function eliminarTransmision($transmisionId, $canalId)
    {
        $canal        = Canal::findOrFail($canalId);
        $stream       = Stream::with('video')->findOrFail($transmisionId);
        $isAssociated = $canal->streams()->where('streams.id', $stream->id)->exists();
        if (! $isAssociated) {
            return response()->json(['message' => 'La transmisión no está asociada a este canal o no tienes permiso.'], 403);
        }
        $video = $stream->video;
        if (! $video) {
            return response()->json(['message' => 'Error interno: La transmisión existe, pero no tiene un video asociado.'], 500);
        }
        $this->eliminarArchivoStream($stream);
        if ($video->miniatura) {
            Storage::disk('s3')->delete($video->miniatura);
        }
        $stream->delete();
        $video->delete();
        return response()->json(['message' => 'Transmisión, Video y Miniatura eliminados con éxito.']);
    }

    private function eliminarArchivoStream(Stream $stream)
    {
        $linkIdentifier = optional($stream->video)->link;

        if ($linkIdentifier) {
            $rutaArchivoMp4 = "streams/{$linkIdentifier}.mp4";
            if (Storage::disk('s3')->exists($rutaArchivoMp4)) {
                Storage::disk('s3')->delete($rutaArchivoMp4);
                return true;
            }
        }
        return false;
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

    public function activarStream(Request $request)
    {
        try {
            $streamId = $request->input('stream_id');
            $userId   = $request->input('user_id');

            if (! $streamId || ! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren stream_id y user_id.',
                ], 400);
            }

            $stream = $this->autorizarYObtenerStream($streamId, $userId);
            $canal  = optional(optional($stream->video)->canal);
            $video  = $stream->video;

            if (! $video) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stream sin video asociado.',
                ], 404);
            }

            if ($stream->activo == true) {
                return response()->json([
                    'success' => false,
                    'message' => 'El stream ya está marcado como activo (Stream.activo=TRUE).',
                ], 200);
            }

            if ($video->estado === 'DIRECTO') {
                return response()->json([
                    'success' => false,
                    'message' => 'El stream ya estaba activo (estado DIRECTO).',
                ], 200);
            }

            if ($video->estado === 'FINALIZADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede activar el stream. El video asociado ya se encuentra en estado FINALIZADO y no puede ser reutilizado.',
                ], 409);
            }

            $streamActivoExistente = Stream::whereHas('video', function ($query) use ($canal) {
                $query->where('canal_id', $canal->id);
            })
                ->where('activo', true)
                ->first();

            if ($streamActivoExistente && $streamActivoExistente->id !== (int) $streamId) {
                return response()->json([
                    'success'          => false,
                    'message'          => 'Ya existe un stream activo para este canal. Finalícelo primero.',
                    'active_stream_id' => $streamActivoExistente->id,
                ], 409);
            }

            $stream->update(['activo' => true]);
            $video->update(['estado' => 'PROGRAMADO']);

            return response()->json([
                'success'   => true,
                'message'   => 'Stream marcado como activo (Stream.activo=TRUE). Esperando conexión de OBS para iniciar.',
                'stream_id' => $stream->id,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stream o Canal no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Acceso denegado: El usuario no es el dueño del canal.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            Log::error("Error al iniciar stream: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al iniciar el stream.',
            ], 500);
        }
    }

    public function desactivarStream(Request $request)
    {
        try {
            $streamId = $request->input('stream_id');
            $userId   = $request->input('user_id');

            if (! $streamId || ! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren stream_id y user_id.',
                ], 400);
            }

            $stream = $this->autorizarYObtenerStream($streamId, $userId);
            $video  = $stream->video;

            if (! $video) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stream sin video asociado.',
                ], 404);
            }

            if ($stream->activo == false) {
                return response()->json([
                    'success' => true,
                    'message' => 'El stream ya estaba inactivo.',
                ], 200);
            }

            if ($video->estado === 'DIRECTO') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar manualmente un stream en estado DIRECTO. Desconecte OBS para finalizar la transmisión.',
                ], 409);
            }

            $stream->update(['activo' => false]);

            return response()->json([
                'success'   => true,
                'message'   => 'Stream desactivado correctamente (Stream.activo=FALSE).',
                'stream_id' => $stream->id,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stream o Canal no encontrado.',
            ], 404);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Acceso denegado: El usuario no es el dueño del canal.') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            Log::error("Error al desactivar stream: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al desactivar el stream.',
            ], 500);
        }
    }

    private function autorizarYObtenerStream(int $streamId, $userId): Stream
    {
        $stream = Stream::with('video.canal')->findOrFail($streamId);
        $canal  = optional(optional($stream->video)->canal);

        if (! $canal) {
            throw new \Exception('El stream no está vinculado a un canal válido.');
        }

        if ($canal->user_id !== (int) $userId) {
            throw new \Exception('Acceso denegado: El usuario no es el dueño del canal.');
        }

        return $stream;
    }

    public function iniciarStream(Request $request)
    {
        $streamKey = $request->input('name');

        if (! $streamKey) {
            Log::warning('Intento de publicación sin stream key.');
            return response('Forbidden', 403);
        }

        try {
            $canal = Canal::where('stream_key', $streamKey)->first();

            if (! $canal) {
                Log::warning("Publicación rechazada. Stream Key no encontrado: [{$streamKey}]");
                return response('Not Found', 404);
            }
            $streamActivo = Stream::whereHas('video', function ($query) use ($canal) {
                $query->where('canal_id', $canal->id);
            })
                ->where('activo', true)
                ->with('video')
                ->first();

            if (! $streamActivo) {
                Log::warning("Publicación denegada. El canal [{$canal->id}] existe, pero no tiene un Stream activo (activo=true) en la plataforma.");
                return response('Forbidden - Stream Not Activated', 403);
            }
            $video = $streamActivo->video;

            if ($video && $video->estado !== 'DIRECTO') {
                $video->update(['estado' => 'DIRECTO']);
                Log::info("Estado del Video ID [{$video->id}] actualizado a DIRECTO por la conexión RTMP.");
            } elseif (! $video) {
                Log::error("Stream activo [{$streamActivo->id}] sin registro de Video asociado. Publicación denegada.");
                return response('Internal Error: Missing Video', 500);
            }
            Log::info("Publicación de stream autorizada para Stream Key: [{$streamKey}] (Stream activo=true en DB)");
            return response('OK', 200);

        } catch (Exception $e) {
            Log::error("Error de DB al autenticar stream key: " . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }

    public function finalizarStream(Request $request)
    {
        $streamKey = $request->input('name');

        if (! $streamKey) {
            Log::error('Finalización automática fallida: No se recibió la stream key (name).');
            return response('Error: Stream Key Missing', 400);
        }

        try {
            $canal = Canal::where('stream_key', $streamKey)->firstOrFail();

            $stream = Stream::whereHas('video', function ($query) use ($canal) {
                $query->where('canal_id', $canal->id)->where('estado', 'DIRECTO');
            })
                ->where('activo', true)
                ->with('video')
                ->first();

            if (! $stream) {
                Log::info("Finalización automática ignorada: Stream Key [{$streamKey}] sin stream ACTIVO/DIRECTO encontrado.");
                return response('OK', 200);
            }

            $video    = $stream->video;
            $streamId = $stream->id;

            if (! $video) {
                throw new ModelNotFoundException("Stream ID {$streamId} sin video asociado.");
            }

            if ($video->estado !== 'DIRECTO') {
                if ($stream->activo === true) {
                    $stream->update(['activo' => false]);
                }
                Log::info("Finalización automática ignorada: Stream ID {$streamId} ya estaba en estado {$video->estado}.");
                return response('OK', 200);
            }

            $video->update(['estado' => 'FINALIZADO']);
            $stream->update(['activo' => false]);

            try {
                $vodData = $this->subirVideoDeStream($streamId);

                $host   = $this->obtenerHostMinio();
                $bucket = $this->obtenerBucket();
                $video  = $vodData['video'];
                $rutaS3 = $vodData['rutaS3'];

                Log::info("Finalización y VOD exitoso para Stream Key: [{$streamKey}] (Stream ID: {$streamId})");

                return response()->json([
                    'message'       => 'Stream finalizado y video VOD subido correctamente.',
                    'video_url'     => $this->obtenerUrlArchivo($rutaS3, $host, $bucket),
                    'miniatura_url' => $this->obtenerUrlArchivo($video->miniatura, $host, $bucket),
                    'video_id'      => $video->id,
                ], 200);

            } catch (\Exception $e) {
                if ($e->getMessage() === 'STREAM_MISSING_VIDEO') {
                    Log::warning("Stream finalizado. Advertencia VOD: Stream ID {$streamId} sin video asociado para procesar.");
                    return response('OK, VOD Warning', 200);
                }

                Log::error("Error VOD al finalizar stream automático (Stream Key: {$streamKey}): " . $e->getMessage());
                return response('VOD Processing Failed', 500);
            }

        } catch (ModelNotFoundException $e) {
            Log::warning("Finalización automática fallida: Canal o Stream no encontrado para Stream Key [{$streamKey}].");
            return response('Not Found', 404);

        } catch (\Exception $e) {
            Log::error("Error general al finalizar stream automático (Stream Key: {$streamKey}): " . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }

    private function subirVideoDeStream($streamId): array
    {
        $stream = $this->obtenerStreamPorId($streamId);

        if (! $stream->video) {
            throw new \Exception('STREAM_MISSING_VIDEO');
        }

        $archivoFLV = $this->obtenerArchivoFLVDesdeMinio($stream);
        $rutaMP4    = $this->convertirFLVaMP4($archivoFLV);
        $rutaS3     = $this->subirVideoConvertidoAMinio($stream, $rutaMP4, $archivoFLV);
        $video      = $this->finalizarRegistroDeVideo($stream, $rutaS3, $rutaMP4);

        return [
            'rutaS3' => $rutaS3,
            'video'  => $video,
        ];
    }

    private function obtenerStreamPorId($streamId)
    {
        $stream = Stream::with('video')->find($streamId);
        if (! $stream) {
            throw new \Exception('No se encontró un stream con el ID proporcionado.');
        }
        return $stream;
    }

    private function obtenerArchivoFLVDesdeMinio(Stream $stream): string
    {
        $directorio = 'streams';
        $canal      = optional(optional($stream->video)->canal);

        if (! $canal) {
            throw new \Exception('El stream no está vinculado a un canal válido (falta Video o Canal).');
        }

        $streamKey = $canal->stream_key;

        if (! $streamKey) {
            throw new \Exception('No se pudo encontrar el stream_key del canal para localizar el archivo FLV.');
        }
        $patron            = '/' . preg_quote($streamKey . '-', '/') . '\d+\.flv$/';
        $archivoEncontrado = collect(Storage::disk('s3')->allFiles($directorio))
            ->first(function ($archivo) use ($patron) {
                return preg_match($patron, $archivo);
            });

        if ($archivoEncontrado) {
            return $archivoEncontrado;
        }
        $rutaSimple = "{$directorio}/{$streamKey}.flv";
        if (Storage::disk('s3')->exists($rutaSimple)) {
            return $rutaSimple;
        }
        throw new \Exception("Archivo FLV no encontrado en MinIO en la ruta esperada. Patrón buscado: [{$streamKey}-<timestamp>.flv]");
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
        $carpeta       = "videos/{$stream->video->canal_id}";
        $nombreArchivo = bin2hex(random_bytes(16)) . '.mp4';
        $rutaS3        = "{$carpeta}/{$nombreArchivo}";

        Storage::disk('s3')->put($rutaS3, file_get_contents($rutaArchivo), [
            'Metadata' => [
                'titulo_video' => $stream->video->titulo,
                'descripcion'  => $stream->video->descripcion,
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

    private function finalizarRegistroDeVideo(Stream $stream, string $rutaVideo, string $rutaMP4): Video
    {
        $video = $stream->video;
        if (! $video) {
            throw new \Exception('No se pudo encontrar el registro de Video para actualizar.');
        }

        $duracion = $this->obtenerDuracionDeVideo($rutaMP4);

        $video->update([
            'link'     => $rutaVideo,
            'duracion' => $duracion,
        ]);

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

    private function crearDirectorioSiNoExiste(string $directorio): void
    {
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
    }

    public function entrarView(Request $request, $streamId)
    {
        $service = new StreamViewerService();
        $count   = $service->añadirViewer((int) $streamId);

        return response()->json([
            'ok'      => true,
            'viewers' => $count,
        ]);
    }

    public function salirView(Request $request, $streamId)
    {
        $service = new StreamViewerService();
        $count   = $service->eliminarViewer((int) $streamId);

        return response()->json([
            'ok'      => true,
            'viewers' => $count,
        ]);
    }

    public function hlsEvent(Request $request)
    {
        $event = $request->input('call');
        $name  = $request->input('name');
        $addr  = $request->input('addr', $request->ip());

        if (! $name || ! in_array($event, ['on_play', 'on_play_done'])) {
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
            'stream'  => $name,
            'viewers' => max(0, Redis::get($redisKey) ?? 0),
            'event'   => $event,
            'ip'      => $addr,
        ]);
    }

    public function status($key)
    {
        $base = '/mnt/hls/' . $key;

        $playlist = $base . '/index.m3u8';

        if (! file_exists($playlist)) {
            return response()->json([
                'online'  => false,
                'message' => 'Stream not found or offline',
            ]);
        }

        $content = file_get_contents($playlist);

        preg_match_all('/(\d+)\.ts/', $content, $matches);

        if (empty($matches[1])) {
            return response()->json([
                'online'   => true,
                'bitrate'  => null,
                'segments' => 0,
                'message'  => 'Waiting for segments...',
            ]);
        }

        $lastSegment = max($matches[1]);
        $segmentPath = $base . '/' . $lastSegment . '.ts';

        $fileSizeBytes = file_exists($segmentPath) ? filesize($segmentPath) : 0;
        $duration      = 3;

        $bitrateKbps = $duration > 0
            ? round(($fileSizeBytes * 8 / 1024) / $duration)
            : null;

        return response()->json([
            'online'          => true,
            'segments'        => count($matches[1]),
            'current_segment' => $lastSegment,
            'bitrate_kbps'    => $bitrateKbps,
            'playlist'        => "/hls/{$key}/index.m3u8",
        ]);
    }

    public function metricsHls($key)
    {
        $base         = "/mnt/hls/{$key}";
        $playlistPath = "{$base}/index.m3u8";

        if (! file_exists($playlistPath)) {
            return response()->json([
                'online'  => false,
                'message' => 'Stream offline',
            ]);
        }

        $content = file_get_contents($playlistPath);

        preg_match_all('/(.*\.ts)/', $content, $matches);

        if (empty($matches[1])) {
            return response()->json([
                'online'   => true,
                'segments' => 0,
                'message'  => 'Waiting for segments...',
            ]);
        }

        $segments    = $matches[1];
        $lastSegment = end($segments);
        $segmentPath = "{$base}/{$lastSegment}";

        $size        = filesize($segmentPath);
        $durationSec = 3;
        $bitrateKbps = round(($size * 8 / 1024) / $durationSec);

        $ffprobe  = "ffprobe -v quiet -print_format json -show_streams \"$segmentPath\"";
        $metaJson = shell_exec($ffprobe);
        $meta     = json_decode($metaJson, true);

        $videoStream = collect($meta['streams'])->firstWhere('codec_type', 'video');

        $width  = $videoStream['width'] ?? null;
        $height = $videoStream['height'] ?? null;
        $fps    = null;

        if (isset($videoStream['r_frame_rate'])) {
            list($num, $den) = explode('/', $videoStream['r_frame_rate']);
            $fps             = round($num / $den);
        }

        $latencySeconds = count($segments) * $durationSec;

        $playlistAgeSeconds = time() - filemtime($playlistPath);

        return response()->json([
            'online'          => true,
            'segments'        => count($segments),
            'current_segment' => $lastSegment,
            'bitrate_kbps'    => $bitrateKbps,
            'resolution'      => "{$width}x{$height}",
            'fps' => $fps,
            'latency_seconds' => $latencySeconds,
            'playlist_age' => $playlistAgeSeconds,
            'playlist' => "/hls/{$key}/index.m3u8",
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
        $count   = $service->heartbeat($streamId, $userId);

        broadcast(new ViewerStream($streamId, [
            'type'  => 'viewer_count',
            'count' => $count,
        ]));

        return response()->json(['ok' => true]);
    }

}
