<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EtiquetaController;
use App\Models\Canal;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use App\Helpers\FFMpegHelper;


class VideoController extends Controller
{

    public function mostrarTodosLosVideos(Request $request)
    {
        $videos = Video::with('canal.user', 'etiquetas')
            ->withCount('visitas')
            ->take(20)
            ->get();
        return response()->json($videos, 200);
    }

    public function mostrarInformacionVideo($idVideo)
    {
        $video = Video::with('canal.user', 'etiquetas')
            ->withCount('visitas')
            ->findOrFail($idVideo);
        return response()->json($video, 200);
    }

    public function listarVideosPorNombre(Request $request, $nombre)
    {
        $videos = Video::with('canal.user', 'etiquetas')
            ->withCount('visitas')
            ->where('titulo', 'LIKE', '%' . $nombre . '%')
            ->take(20)
            ->get();

        return response()->json($videos, 200);
    }

    public function subirVideo(Request $request, $canalId)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'etiquetas' => 'array',
        ]);

        if ($request->hasFile('video')) {
            $canal = Canal::findOrFail($canalId);
            $folderPath = 'videos/' . $canalId;
            $rutaVideo = $request->file('video')->store($folderPath, 's3');
            $urlVideo = str_replace('minio', 'localhost', Storage::disk('s3')->url($rutaVideo));

            $urlMiniatura = $this->generarMiniatura($request->file('video'), $canalId);

            $video = new Video([
                'titulo' => $request->titulo,
                'descripcion' => $request->descripcion,
                'link' => $urlVideo,
                'miniatura' => $urlMiniatura,

            ]);
            $video->canal_id = $canal->id;
            $video->save();

            if ($request->has('etiquetas')) {
                $etiquetasController = new EtiquetaController();
                $etiquetasController->asignarEtiquetas($request, $video->id);
            }

            return response()->json($video, 201);
        } else {
            return response()->json(['error' => 'No se proporcionó ningún archivo de video'], 400);
        }
    }
 
   
    private function generarMiniatura($videoFile, $canalId) {
        $videoPath = $videoFile->getRealPath();
        $miniaturaNombre = uniqid() . '.jpg';
        $miniaturaLocalRuta = '/tmp/' . $miniaturaNombre;
        $miniaturaS3Ruta = 'miniaturas/' . $canalId . '/miniaturas/' . $miniaturaNombre;
        $ffmpeg = FFMpegHelper::crearFFMpeg();
        $video = $ffmpeg->open($videoPath);
        $duracionTotal = $video->getStreams()->videos()->first()->get('duration');
        $tiempoAleatorio = rand(0, $duracionTotal);
        $frame = $video->frame(TimeCode::fromSeconds($tiempoAleatorio));
        $frame->save($miniaturaLocalRuta);
        Storage::disk('s3')->put($miniaturaS3Ruta, file_get_contents($miniaturaLocalRuta));
        unlink($miniaturaLocalRuta);
        return str_replace('minio', 'localhost', Storage::disk('s3')->url($miniaturaS3Ruta));
    }


    public function bajaLogicaVideo($idVideo)
    {
        $video = Video::findOrFail($idVideo);
        $video->delete();
        $video->save();
        return response()->json(['message' => 'Video dado de baja correctamente'], 200);
    }

    public function editarVideo(Request $request, $idVideo)
    {
        $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'sometimes|required|string',
            'video' => 'sometimes|required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-flv,video/webm|max:120000',
            'miniatura' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:10240'
        ]);

        $video = Video::findOrFail($idVideo);
        $oldVideoPath = $this->getStoragePath($video->link);
        $oldMiniaturaPath = $this->getStoragePath($video->miniatura);

        $folderPath = 'videos/' . $video->canal_id;
        $folderPathMiniatura = 'miniaturas/' . $video->canal_id;

        if ($request->has('titulo')) {
            $video->titulo = $request->titulo;
        }
        if ($request->has('descripcion')) {
            $video->descripcion = $request->descripcion;
        }
        if ($request->hasFile('video')) {
            if ($oldVideoPath) {
                Storage::disk('s3')->delete($oldVideoPath);
            }
            $rutaVideo = $request->file('video')->store($folderPath, 's3');
            $video->link = str_replace('minio', 'localhost', Storage::disk('s3')->url($rutaVideo));

            $urlMiniatura = $this->generarMiniatura($request->file('video'), $video->canal_id);
            if ($oldMiniaturaPath) {
                Storage::disk('s3')->delete($oldMiniaturaPath);
            }
            $video->miniatura = $urlMiniatura;
        }

        if ($request->hasFile('miniatura')) {
            if ($oldMiniaturaPath) {
                Storage::disk('s3')->delete($oldMiniaturaPath);
            }
            $rutaMiniatura = $request->file('miniatura')->store($folderPathMiniatura, 's3');
            $video->miniatura = str_replace('minio', 'localhost', Storage::disk('s3')->url($rutaMiniatura));
        }

        $video->save();

        return response()->json(['message' => 'Video actualizado correctamente'], 200);
    }

    private function getStoragePath($url)
    {
        $urlParts = parse_url($url);
        return ltrim($urlParts['path'], '/');
    }
}
