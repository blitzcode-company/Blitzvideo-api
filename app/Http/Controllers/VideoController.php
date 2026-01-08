<?php
namespace App\Http\Controllers;

use App\Helpers\FFMpegHelper;
use App\Models\Canal;
use App\Models\Etiqueta;
use App\Models\Publicidad;
use App\Models\Video;
use Carbon\Carbon;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class VideoController extends Controller
{

    public function mostrarTodosLosVideos()
    {
        $videos = $this->obtenerVideosConRelaciones();
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }

    private function ajustarRutasDeVideos($videos)
    {
        $host   = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
        $bucket = env('AWS_BUCKET') . '/';
        if ($videos instanceof Collection) {
            $videos->each(fn($video) => $this->ajustarRutasDeVideo($video, $host, $bucket));
        } else {
            $this->ajustarRutasDeVideo($videos, $host, $bucket);
        }
    }

    private function ajustarRutasDeVideo($video, $host, $bucket)
    {
        $video->miniatura = $this->obtenerUrlArchivo($video->miniatura, $host, $bucket);
        $video->link      = $this->obtenerUrlArchivo($video->link, $host, $bucket);
        if (isset($video->canal->user)) {
            $video->canal->user->foto = $this->obtenerUrlArchivo($video->canal->user->foto, $host, $bucket);
        }
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

    private function obtenerVideosConRelaciones()
    {
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->where('bloqueado', false)
            ->where('acceso', 'publico')
            ->whereDoesntHave('publicidad')
            ->inRandomOrder()
            ->take(8)
            ->get()
            ->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
    }

    private function datosDeRelacionesDeVideo()
    {
        return [
            'canal:id,nombre,descripcion,user_id',
            'canal.user:id,name,foto,email',
            'etiquetas:id,nombre',
        ];
    }

    private function obtenerContadoresDePuntuaciones()
    {
        return [
            'puntuaciones as puntuacion_1' => fn($query) => $query->where('valora', 1),
            'puntuaciones as puntuacion_2' => fn($query) => $query->where('valora', 2),
            'puntuaciones as puntuacion_3' => fn($query) => $query->where('valora', 3),
            'puntuaciones as puntuacion_4' => fn($query) => $query->where('valora', 4),
            'puntuaciones as puntuacion_5' => fn($query) => $query->where('valora', 5),
            'visitas',
        ];
    }

    public function listarVideosPorNombre($nombre)
    {
        $videos = $this->obtenerVideosPorNombre($nombre);
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }

    private function obtenerVideosPorNombre($nombre)
    {
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->where('titulo', 'LIKE', '%' . $nombre . '%')
            ->where('bloqueado', false)
            ->where('acceso', 'publico')
            ->take(8)
            ->get()
            ->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
    }

    public function mostrarInformacionVideo($idVideo)
    {
        $video = $this->obtenerVideoPorId($idVideo);
        if ($video->bloqueado) {
            return $this->respuestaError('El video está bloqueado y no se puede acceder.', 403);
        }
        
        $this->ajustarRutasDeVideos($video);
        $video->promedio_puntuaciones = $video->puntuacion_promedio;
        return response()->json($video, 200);
    }

    private function obtenerVideoPorId($idVideo)
    {
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->findOrFail($idVideo);
    }

    private function respuestaError($mensaje, $codigo)
    {
        return response()->json(['error' => $mensaje, 'code' => $codigo], $codigo);
    }

    public function subirVideo(Request $request, $canalId)
    {
        $this->validarSubidaDeVideo($request);

        if (! $request->hasFile('video')) {
            return response()->json(['error' => 'No se proporcionó ningún archivo de video'], 400);
        }
        $canal     = Canal::findOrFail($canalId);
        $videoData = $this->procesarVideo($request->file('video'), $request, $canalId);
        $video     = $this->crearNuevoVideo($request, $canal, $videoData);

        if ($request->has('etiquetas')) {
            $this->asignarEtiquetas($request, $video->id);
        }
        $notificacionController = new NotificacionController();
        $notificacionController->crearNotificacionDeVideoSubido($canal->user_id, $video->id, $canal->nombre);
        return response()->json($video, 201);
    }

    private function validarSubidaDeVideo($request)
    {
        $rules = [
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'required|string',
            'video'       => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'etiquetas'   => 'array',
        ];

        $this->validarRequest($request, $rules);
    }

    private function validarRequest($request, $rules)
    {
        $request->validate($rules);
    }

    private function procesarVideo($videoFile, $request, $canalId)
    {
        $rutaVideo = $this->guardarArchivo($videoFile, 'videos/' . $canalId);


        if ($request->hasFile('miniatura')) {
                $miniaturaFile = $request->file('miniatura');
                if ($miniaturaFile->isValid()) {
                    $urlMiniatura = $this->guardarArchivo($miniaturaFile, 'miniaturas/' . $canalId);
                }
            }

        if (!isset($urlMiniatura)) {
            $urlMiniatura = $this->generarMiniatura($videoFile, $canalId);
        }

        $duracion     = $this->obtenerDuracionDeVideo($videoFile);
        return ['rutaVideo' => $rutaVideo, 'urlMiniatura' => $urlMiniatura, 'duracion' => $duracion];
    }

    private function guardarArchivo($archivo, $ruta)
    {
        return $archivo->store($ruta, 's3');
    }

    private function generarMiniatura($videoFile, $canalId)
    {
        $miniaturaNombre = uniqid() . '.jpg';
        $miniaturaRuta   = 'miniaturas/' . $canalId . '/' . $miniaturaNombre;
        $this->procesarMiniatura($videoFile->getRealPath(), $miniaturaRuta);
        return $miniaturaRuta;
    }

    private function procesarMiniatura($videoPath, $miniaturaRuta)
    {
        $miniaturaLocalRuta = $this->extraerFrameAleatorio($videoPath);
        $this->subirYEliminarArchivo($miniaturaLocalRuta, $miniaturaRuta);
    }

    private function extraerFrameAleatorio($videoPath)
    {
        $miniaturaLocalRuta = '/tmp/' . uniqid() . '.jpg';
        $ffmpeg             = FFMpegHelper::crearFFMpeg();
        $video              = $ffmpeg->open($videoPath);
        $duracionTotal      = $video->getStreams()->videos()->first()->get('duration');
        $tiempoAleatorio    = rand(0, $duracionTotal);
        $video->frame(TimeCode::fromSeconds($tiempoAleatorio))->save($miniaturaLocalRuta);
        return $miniaturaLocalRuta;
    }

    private function subirYEliminarArchivo($archivoLocalRuta, $archivoS3Ruta)
    {
        Storage::disk('s3')->put($archivoS3Ruta, file_get_contents($archivoLocalRuta));
        unlink($archivoLocalRuta);
    }

    private function obtenerDuracionDeVideo($videoFile)
    {
        $ffmpeg                = FFMpegHelper::crearFFMpeg();
        $video                 = $ffmpeg->open($videoFile->getRealPath());
        $duracionTotalDelVideo = $video->getStreams()->videos()->first()->get('duration');
        return $duracionTotalDelVideo;
    }

    private function crearNuevoVideo($request, $canal, $videoData)
    {
        $descripcion = $this->procesarDescripcion($request->descripcion);

        $video = new Video([
            'titulo'      => $request->titulo,
            'descripcion' => $descripcion,
            'link'        => $videoData['rutaVideo'],
            'miniatura'   => $videoData['urlMiniatura'],
            'duracion'    => $videoData['duracion'],
            'canal_id'    => $canal->id,
        ]);
        $video->save();
        return $video;
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
            $url = $matches[0];
            $display = htmlspecialchars(substr($url, 0, 60)) . (strlen($url) > 60 ? '...' : '');
            return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $display . '</a>';
        }, $text);
    }
    
    private function asignarEtiquetas($request, $videoId)
    {
        $etiquetasController = new EtiquetaController();
        $etiquetasController->asignarEtiquetas($request, $videoId);
    }

    public function editarVideo(Request $request, $idVideo)
    {
        $this->validarEdicionDeVideo($request);
        $video = Video::with('canal.user')->findOrFail($idVideo);
        $this->procesarEdicionDeVideo($request, $video);
        return response()->json(['message' => 'Video actualizado correctamente', 'video' => $video], 200);
    }

    private function validarEdicionDeVideo($request)
    {
        $rules = [
            'titulo'      => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|required|string',
            'acceso'      => 'sometimes|required|in:privado,publico',
            'video'       => 'sometimes|required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'miniatura'   => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:10240',
        ];
        $this->validarRequest($request, $rules);
    }

    private function procesarEdicionDeVideo($request, $video)
    {
        $oldVideoPath     = $this->obtenerRutaDeAlmacenamiento($video->link);
        $oldMiniaturaPath = $this->obtenerRutaDeAlmacenamiento($video->miniatura);
        $this->actualizarCampos($request, $video);
        if ($request->hasFile('video')) {
            $this->procesarNuevoArchivo($request->file('video'), $video, 'video', $oldVideoPath, $oldMiniaturaPath);
        }
        if ($request->hasFile('miniatura')) {
            $this->procesarNuevoArchivo($request->file('miniatura'), $video, 'miniatura', $oldMiniaturaPath);
        }
        if ($request->has('etiquetas')) {
            $video->etiquetas()->sync($request->etiquetas);
        }
        $video->save();
    }

    private function obtenerRutaDeAlmacenamiento($url)
    {
        return $url ? ltrim(parse_url($url, PHP_URL_PATH), '/') : null;
    }

    private function actualizarCampos($request, $video)
    {
        $video->titulo      = $request->input('titulo', $video->titulo);
        $video->descripcion = $request->input('descripcion', $video->descripcion);
        $video->acceso = $request->input('acceso', $video->acceso);

    }

    private function procesarNuevoArchivo($nuevoArchivo, $video, $tipo, $oldArchivoPath, $oldMiniaturaPath = null)
    {
        $folderPath = $tipo === 'video' ? 'videos/' . $video->canal_id : 'miniaturas/' . $video->canal_id;
        if ($oldArchivoPath) {
            Storage::disk('s3')->delete($oldArchivoPath);
        }
        $rutaArchivo = $nuevoArchivo->store($folderPath, 's3');
        if ($tipo === 'video') {
            $video->link      = $rutaArchivo;
            $video->miniatura = $this->generarMiniatura($nuevoArchivo, $video->canal_id);
            if ($oldMiniaturaPath) {
                Storage::disk('s3')->delete($oldMiniaturaPath);
            }
        } else {
            $video->miniatura = $rutaArchivo;
        }
    }

    public function bajaLogicaVideo($idVideo)
    {
        $video = Video::findOrFail($idVideo);
        $video->delete();
        return response()->json(['message' => 'Video dado de baja correctamente'], 200);
    }

    public function eliminarMultiplesVideos(Request $request)
    {
        $request->validate([
            'video_ids' => 'required|array|min:1',
            'video_ids.*' => 'required|integer|exists:videos,id'
        ]);

        $videoIds = $request->input('video_ids');
        $videosEliminados = 0;
        $errores = [];

        foreach ($videoIds as $videoId) {
            try {
                $video = Video::findOrFail($videoId);
                $video->delete();
                $videosEliminados++;
            } catch (\Exception $e) {
                $errores[] = [
                    'video_id' => $videoId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response = [
            'success' => true,
            'message' => "Se eliminaron {$videosEliminados} video(s) correctamente",
            'videos_eliminados' => $videosEliminados,
            'total_solicitados' => count($videoIds)
        ];

        if (!empty($errores)) {
            $response['errores'] = $errores;
        }

        return response()->json($response, 200);
    }

    public function listarVideosRecomendados($userId)
    {
        $videos = $this->obtenerVideosRecomendados($userId);
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }

    public function listarVideosMasVistos()
    {
        $videos = $this->obtenerVideosMasVistos();
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }
    
    private function obtenerVideosMasVistos()
    {
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->withCount('visitas')
            ->where('bloqueado', false)
            ->where('acceso', 'publico')
            ->whereDoesntHave('publicidad') 
            ->orderBy('visitas_count', 'desc')
            ->take(8)
            ->get()
            ->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
    }

    private function obtenerVideosRecomendados($userId)
    {
        $categoriasMasVisitadas = $this->obtenerCategoriasMasVisitadasPorUsuario($userId);
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->withCount('visitas')
            ->where('bloqueado', false)
            ->where('acceso', 'publico')
            ->whereHas('etiquetas', function ($query) use ($categoriasMasVisitadas) {
                $query->whereIn('etiquetas.id', $categoriasMasVisitadas);
            })
            ->orderBy('visitas_count', 'desc')
            ->take(8)
            ->get()
            ->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
    }

    private function obtenerCategoriasMasVisitadasPorUsuario($userId)
    {
        return Etiqueta::select('etiquetas.id')
            ->join('etiqueta_video', 'etiquetas.id', '=', 'etiqueta_video.etiqueta_id')
            ->join('visitas', 'etiqueta_video.video_id', '=', 'visitas.video_id')
            ->where('visitas.user_id', $userId)
            ->groupBy('etiquetas.id')
            ->orderByRaw('COUNT(visitas.id) DESC')
            ->pluck('etiquetas.id');
    }

    public function listarTendencias()
    {
        $videos = $this->obtenerVideosTendencias();
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }

    private function obtenerVideosTendencias()
    {
        $fechaLimite = Carbon::now()->subWeek();
        return Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->withCount('visitas')
            ->where('bloqueado', false)
            ->where('acceso', 'publico')
            ->whereDoesntHave('publicidad')
            ->whereHas('visitas', function ($query) use ($fechaLimite) {
                $query->where('created_at', '>=', $fechaLimite);
            })
            ->orderBy('visitas_count', 'desc')
            ->take(8)
            ->get()->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
        return $videos;
    }

    public function listarVideosRelacionados($videoId)
    {
        $videos = $this->obtenerVideosRelacionados($videoId);
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }
    private function obtenerVideosRelacionados($videoId)
    {
        $etiquetasDelVideo = $this->obtenerEtiquetasDelVideo($videoId);
        return $this->obtenerVideosPorEtiquetas($etiquetasDelVideo, $videoId);
    }

    private function obtenerEtiquetasDelVideo($videoId)
    {
        return Etiqueta::select('etiquetas.id')
            ->join('etiqueta_video', 'etiquetas.id', '=', 'etiqueta_video.etiqueta_id')
            ->where('etiqueta_video.video_id', $videoId)
            ->pluck('etiquetas.id');
    }

    private function obtenerVideosPorEtiquetas($etiquetas, $excluirVideoId = null)
    {
        $query = Video::with($this->datosDeRelacionesDeVideo())
            ->withCount($this->obtenerContadoresDePuntuaciones())
            ->withCount('visitas')
            ->where('bloqueado', false)
            ->where('acceso', 'publico');

        if ($excluirVideoId) {
            $query->where('id', '!=', $excluirVideoId);
        }
        return $query->whereHas('etiquetas', function ($query) use ($etiquetas) {
            $query->whereIn('etiquetas.id', $etiquetas);
        })
            ->orderBy('visitas_count', 'desc')
            ->get()
            ->each(fn($video) => $video->promedio_puntuaciones = $video->puntuacion_promedio);
    }

    public function listarVideosPorEtiqueta($etiquetaId)
    {
        $etiquetas = collect([$etiquetaId]);
        $videos    = $this->obtenerVideosPorEtiquetas($etiquetas);
        $this->ajustarRutasDeVideos($videos);
        return response()->json($videos, 200);
    }

    public function mostrarPublicidad()
    {
        $publicidadSeleccionada = $this->seleccionarPublicidad();
        if (! $publicidadSeleccionada) {
            return $this->respuestaError('No hay publicidades disponibles.', 404);
        }
        $videoRelacionado = $this->obtenerVideoRelacionadoDePublicidad($publicidadSeleccionada);
        if (! $videoRelacionado) {
            return $this->respuestaError('No hay videos asociados a esta publicidad.', 404);
        }
        $videoRelacionado->pivot->increment('vistos');
        $video = $this->obtenerVideoPorId($videoRelacionado->id);
        $this->ajustarRutasDeVideos($video);
        $video->promedio_puntuaciones = $video->puntuacion_promedio;
        return response()->json($video, 200);
    }

    private function seleccionarPublicidad()
    {
        $publicidades = Publicidad::with('video')->get();
        if ($publicidades->isEmpty()) {
            return null;
        }
        return $this->seleccionarPublicidadPonderada($publicidades);
    }

    private function seleccionarPublicidadPonderada($publicidades)
    {
        $ponderado = $publicidades->flatMap(function ($publicidad) {
            return array_fill(0, $publicidad->prioridad, $publicidad);
        });
        return $ponderado->random();
    }
    private function obtenerVideoRelacionadoDePublicidad($publicidad)
    {
        return $publicidad->video()->orderBy('pivot_vistos', 'asc')->first();
    }
}
