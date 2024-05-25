<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use App\Models\Canal;
use Illuminate\Support\Facades\Log;

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
        ]);

        $canal = Canal::findOrFail($canalId);
        $folderPath = 'videos/' . $canalId;
        $rutaVideo = $request->file('video')->store($folderPath, 's3');
        $urlVideo = Storage::disk('s3')->url($rutaVideo);
        $video = new Video([
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'link' => $urlVideo,
        ]);
        $video->canal_id = $canal->id;
        $video->save();
        return response()->json($video, 201);
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
