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
        $rutaVideo = $request->file('video')->store('videos', 'public');
        $urlVideo = Storage::disk('public')->path($rutaVideo);
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
        // unlink($video->link); // Debería borrar el video si la baja es logica?
        return response()->json(['message' => 'Video dado de baja correctamente'], 200);
    }

    public function editarVideo(Request $request, $idVideo)
    {
        $video = Video::findOrFail($idVideo);
        if ($request->has('titulo')) {
            $video->titulo = $request->titulo;
        }
        if ($request->has('descripcion')) {
            $video->descripcion = $request->descripcion;
        }
        if ($request->hasFile('video')) {
            unlink($video->link);
            $rutaVideo = $request->file('video')->store('videos', 'public');
            $urlVideo = Storage::disk('public')->path($rutaVideo);
            $video->link = $urlVideo;
        }
        $video->save();
        return response()->json(['message' => 'Video actualizado correctamente'], 200);
    }
}
