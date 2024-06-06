<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EtiquetaController;
use App\Models\Canal;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;

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
 
    private function generarMiniatura($videoFile, $canalId)
    {
        $videoPath = $videoFile->getRealPath();
        $miniaturaNombre = uniqid() . '.jpg';
        $miniaturaLocalPath = '/tmp/' . $miniaturaNombre;
        $miniaturaS3Path = 'miniaturas/' . $canalId . '/miniaturas/' . $miniaturaNombre;
    
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => env('FFMPEG_BINARIES'),
            'ffprobe.binaries' => env('FFPROBE_BINARIES'),
        ]);
    
        $video = $ffmpeg->open($videoPath);
    
        $frame = $video->frame(TimeCode::fromSeconds(10));
    
        $frame->save($miniaturaLocalPath);
    
        Storage::disk('s3')->put($miniaturaS3Path, file_get_contents($miniaturaLocalPath));
    
        unlink($miniaturaLocalPath);
    
        return str_replace('minio', 'localhost', Storage::disk('s3')->url($miniaturaS3Path));
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
        ]);
        $video = Video::findOrFail($idVideo);
        $urlParts = parse_url($video->link);
        $oldVideoPath = ltrim($urlParts['path'], '/');
        $folderPath = 'videos/' . $video->canal_id;
        if ($request->has('titulo')) {
            $video->titulo = $request->titulo;
        }
        if ($request->has('descripcion')) {
            $video->descripcion = $request->descripcion;
        }
        if ($request->hasFile('video')) {
            if (!empty($oldVideoPath)) {
                Storage::disk('s3')->delete($oldVideoPath);
            }
            $videoFileName = basename($oldVideoPath);
            $rutaVideo = $request->file('video')->storeAs($folderPath, $videoFileName, 's3');
            $urlVideo = Storage::disk('s3')->url($rutaVideo);
            $video->link = $urlVideo;
        }
        $video->save();
        return response()->json(['message' => 'Video actualizado correctamente'], 200);
    }
}
