<?php

namespace App\Http\Controllers;

use App\Helpers\FFMpegHelper;
use App\Models\Canal;
use App\Models\Video;
use FFMpeg\Coordinate\TimeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function mostrarTodosLosVideos(Request $request)
    {
        $videos = $this->obtenerVideosConRelaciones();
        return response()->json($videos, 200);
    }

    public function mostrarInformacionVideo($idVideo)
    {
        $video = $this->obtenerVideoPorId($idVideo);
        return response()->json($video, 200);
    }

    public function listarVideosPorNombre(Request $request, $nombre)
    {
        $videos = $this->obtenerVideosPorNombre($nombre);
        return response()->json($videos, 200);
    }

    public function subirVideo(Request $request, $canalId)
    {
        $this->validarSubidaDeVideo($request);

        if (!$request->hasFile('video')) {
            return response()->json(['error' => 'No se proporcionó ningún archivo de video'], 400);
        }

        $canal = Canal::findOrFail($canalId);
        $videoData = $this->procesarVideo($request->file('video'), $canalId);

        $video = $this->crearNuevoVideo($request, $canal, $videoData);

        if ($request->has('etiquetas')) {
            $this->asignarEtiquetas($request, $video->id);
        }

        return response()->json($video, 201);
    }

    public function editarVideo(Request $request, $idVideo)
    {
        $this->validarEdicionDeVideo($request);

        $user = $request->user();
        $canalDelUsuario = Canal::where('user_id', $user->id)->firstOrFail();

        $video = Video::findOrFail($idVideo);

        if ($video->canal_id !== $canalDelUsuario->id) {
            return response()->json(['message' => 'No tienes permiso para editar este video.'], 403);
        }

        $oldVideoPath = $this->getStoragePath($video->link);
        $oldMiniaturaPath = $this->getStoragePath($video->miniatura);

        $this->actualizarCampos($request, $video);

        if ($request->hasFile('video')) {
            $newVideoFile = $request->file('video');
            $duracion = $this->obtenerDuracionDeVideo($newVideoFile);
            $this->reemplazarArchivo($newVideoFile, $video, 'video', $oldVideoPath, $oldMiniaturaPath);
            
            $video->duracion = $duracion; 
        }

        if ($request->hasFile('miniatura')) {
            $this->reemplazarArchivo($request->file('miniatura'), $video, 'miniatura', $oldMiniaturaPath);
        }

        $video->save();

        return response()->json(['message' => 'Video actualizado correctamente'], 200);
    }

    public function bajaLogicaVideo($idVideo)
    {
        $video = Video::findOrFail($idVideo);
        $video->delete();
        $video->save();
        return response()->json(['message' => 'Video dado de baja correctamente'], 200);
    }

    public function bloquearVideo($idVideo) {

        $video = Video::findOrFAil($idVideo);
        $video->estado = 'bloqueado';
        $video->save();
        return response()->json(['message' => 'Video bloqueado con exito'], 200);

    }

    private function validarRequest($request, $rules)
    {
        $request->validate($rules);
    }

    private function validarSubidaDeVideo($request)
    {
        $rules = [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'etiquetas' => 'array',
        ];

        $this->validarRequest($request, $rules);
    }

    private function validarEdicionDeVideo($request)
    {
        $rules = [
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|required|string',
            'video' => 'sometimes|required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'miniatura' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:10240',
            'duracion' => 'sometimes|required|int|'
        ];

        $this->validarRequest($request, $rules);
    }

    private function procesarVideo($videoFile, $canalId)
    {
        $rutaVideo = $this->guardarArchivo($videoFile, 'videos/' . $canalId);
        $urlVideo = $this->generarUrl($rutaVideo);
        $urlMiniatura = $this->generarMiniatura($videoFile, $canalId);

        $duracion = $this->obtenerDuracionDeVideo($videoFile);
        
        return ['urlVideo' => $urlVideo, 'urlMiniatura' => $urlMiniatura, 'duracion' => $duracion];
    }

    private function guardarArchivo($archivo, $ruta)
    {
        return $archivo->store($ruta, 's3');
    }

    private function generarUrl($ruta)
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), Storage::disk('s3')->url($ruta));
    }

    private function generarMiniatura($videoFile, $canalId)
    {
        $videoPath = $videoFile->getRealPath();
        $miniaturaNombre = uniqid() . '.jpg';
        $miniaturaLocalRuta = '/tmp/' . $miniaturaNombre;
        $miniaturaS3Ruta = 'miniaturas/' . $canalId . '/' . $miniaturaNombre;

        $this->extraerFrameAleatorio($videoPath, $miniaturaLocalRuta);
        $this->subirArchivoAS3($miniaturaLocalRuta, $miniaturaS3Ruta);
        $this->eliminarArchivoLocal($miniaturaLocalRuta);

        return $this->generarUrl($miniaturaS3Ruta);
    }

    private function obtenerDuracionDeVideo($videoFile) {
        $ffmpeg = FFMpegHelper::crearFFMpeg();
        $video = $ffmpeg->open($videoFile->getRealPath());
        $duracionTotalDelVideo = $video->getStreams()->videos()->first()->get('duration');

        return $duracionTotalDelVideo;
    }

    private function extraerFrameAleatorio($videoPath, $miniaturaLocalRuta)
    {
        $ffmpeg = FFMpegHelper::crearFFMpeg();
        $video = $ffmpeg->open($videoPath);
        $duracionTotal = $video->getStreams()->videos()->first()->get('duration');
        $tiempoAleatorio = rand(0, $duracionTotal);
        $frame = $video->frame(TimeCode::fromSeconds($tiempoAleatorio));
        $frame->save($miniaturaLocalRuta);
    }

    private function subirArchivoAS3($archivoLocalRuta, $archivoS3Ruta)
    {
        Storage::disk('s3')->put($archivoS3Ruta, file_get_contents($archivoLocalRuta));
    }

    private function eliminarArchivoLocal($archivoLocalRuta)
    {
        unlink($archivoLocalRuta);
    }

    private function crearNuevoVideo($request, $canal, $videoData)
    {
        $video = new Video([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'link' => $videoData['urlVideo'],
            'miniatura' => $videoData['urlMiniatura'],
            'duracion' => $videoData['duracion'],
            'canal_id' => $canal->id,
        ]);

        $video->save();
        return $video;
    }

    private function asignarEtiquetas($request, $videoId)
    {
        $etiquetasController = new EtiquetaController();
        $etiquetasController->asignarEtiquetas($request, $videoId);
    }

    private function actualizarCampos($request, $video)
    {
        if ($request->has('titulo')) {
            $video->titulo = $request->titulo;
        }
        if ($request->has('descripcion')) {
            $video->descripcion = $request->descripcion;
        }
    }

    private function reemplazarArchivo($nuevoArchivo, $video, $tipo, $oldArchivoPath, $oldMiniaturaPath = null)
    {
        $folderPath = $tipo === 'video' ? 'videos/' . $video->canal_id : 'miniaturas/' . $video->canal_id;

        if ($oldArchivoPath) {
            Storage::disk('s3')->delete($oldArchivoPath);
        }

        $rutaArchivo = $nuevoArchivo->store($folderPath, 's3');
        $urlArchivo = $this->generarUrl($rutaArchivo);

        if ($tipo === 'video') {
            $video->link = $urlArchivo;
            $video->miniatura = $this->generarMiniatura($nuevoArchivo, $video->canal_id);

            if ($oldMiniaturaPath) {
                Storage::disk('s3')->delete($oldMiniaturaPath);
            }
        } else {
            $video->miniatura = $urlArchivo;
        }
    }

    private function getStoragePath($url)
    {
        $urlParts = parse_url($url);
        return ltrim($urlParts['path'], '/');
    }

    private function obtenerVideosConRelaciones()
    {
        $videos = Video::with([
            'canal:id,nombre,descripcion,user_id',
            'canal.user:id,name,foto,email',
            'etiquetas:id,nombre',
        ])
            ->withCount([
                'puntuaciones as puntuacion_1' => function ($query) {
                    $query->where('valora', 1);
                },
                'puntuaciones as puntuacion_2' => function ($query) {
                    $query->where('valora', 2);
                },
                'puntuaciones as puntuacion_3' => function ($query) {
                    $query->where('valora', 3);
                },
                'puntuaciones as puntuacion_4' => function ($query) {
                    $query->where('valora', 4);
                },
                'puntuaciones as puntuacion_5' => function ($query) {
                    $query->where('valora', 5);
                },
                'visitas',
            ])->get();

        $videos->each(function ($video) {
            $video->promedio_puntuaciones = $video->puntuacion_promedio;
        });

        return $videos;
    }

    private function obtenerVideoPorId($idVideo)
    {
        $video = Video::with([
            'canal:id,nombre,descripcion,user_id',
            'canal.user:id,name,foto,email',
            'etiquetas:id,nombre',
        ])
            ->withCount([
                'puntuaciones as puntuacion_1' => function ($query) {
                    $query->where('valora', 1);
                },
                'puntuaciones as puntuacion_2' => function ($query) {
                    $query->where('valora', 2);
                },
                'puntuaciones as puntuacion_3' => function ($query) {
                    $query->where('valora', 3);
                },
                'puntuaciones as puntuacion_4' => function ($query) {
                    $query->where('valora', 4);
                },
                'puntuaciones as puntuacion_5' => function ($query) {
                    $query->where('valora', 5);
                },
                'visitas',
            ])->findOrFail($idVideo);

        $video->promedio_puntuaciones = $video->puntuacion_promedio;

        return $video;
    }

    private function obtenerVideosPorNombre($nombre)
    {
        $videos = Video::with([
            'canal:id,nombre,descripcion,user_id',
            'canal.user:id,name,foto,email',
            'etiquetas:id,nombre',
        ])
            ->withCount([
                'puntuaciones as puntuacion_1' => function ($query) {
                    $query->where('valora', 1);
                },
                'puntuaciones as puntuacion_2' => function ($query) {
                    $query->where('valora', 2);
                },
                'puntuaciones as puntuacion_3' => function ($query) {
                    $query->where('valora', 3);
                },
                'puntuaciones as puntuacion_4' => function ($query) {
                    $query->where('valora', 4);
                },
                'puntuaciones as puntuacion_5' => function ($query) {
                    $query->where('valora', 5);
                },
                'visitas',
            ])->where('titulo', 'LIKE', '%' . $nombre . '%')->get();

        $videos->each(function ($video) {
            $video->promedio_puntuaciones = $video->puntuacion_promedio;
        });

        return $videos;
    }
}
