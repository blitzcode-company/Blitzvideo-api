<?php

namespace App\Http\Controllers;

use App\Models\Canal;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FFMpegHelper;
use FFMpeg\Coordinate\TimeCode;

class VideoController extends Controller
{
    public function mostrarTodosLosVideos(Request $request)
    {
        $videos = $this->obtenerVideosConRelaciones()->take(20)->get();
        return response()->json($videos, 200);
    }

    public function mostrarInformacionVideo($idVideo)
    {
        $video = $this->obtenerVideoPorId($idVideo);
        return response()->json($video, 200);
    }

    public function listarVideosPorNombre(Request $request, $nombre)
    {
        $videos = $this->obtenerVideosPorNombre($nombre)->take(20)->get();
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

        $video = Video::findOrFail($idVideo);
        $oldVideoPath = $this->getStoragePath($video->link);
        $oldMiniaturaPath = $this->getStoragePath($video->miniatura);

        $this->actualizarCampos($request, $video);

        if ($request->hasFile('video')) {
            $this->reemplazarArchivo($request->file('video'), $video, 'video', $oldVideoPath, $oldMiniaturaPath);
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
        ];

        $this->validarRequest($request, $rules);
    }

    private function procesarVideo($videoFile, $canalId)
    {
        $rutaVideo = $this->guardarArchivo($videoFile, 'videos/' . $canalId);
        $urlVideo = $this->generarUrl($rutaVideo);
        $urlMiniatura = $this->generarMiniatura($videoFile, $canalId);

        return ['urlVideo' => $urlVideo, 'urlMiniatura' => $urlMiniatura];
    }

    private function guardarArchivo($archivo, $ruta)
    {
        return $archivo->store($ruta, 's3');
    }

    private function generarUrl($ruta)
    {
        return str_replace('minio', 'localhost', Storage::disk('s3')->url($ruta));
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
        return Video::with('canal.user', 'etiquetas')
                    ->withCount('visitas');
    }

    private function obtenerVideoPorId($idVideo)
    {
        return Video::with('canal.user', 'etiquetas')
                    ->withCount('visitas')
                    ->findOrFail($idVideo);
    }

    private function obtenerVideosPorNombre($nombre)
    {
        return Video::with('canal.user', 'etiquetas')
                    ->withCount('visitas')
                    ->where('titulo', 'LIKE', '%' . $nombre . '%');
    }
}
