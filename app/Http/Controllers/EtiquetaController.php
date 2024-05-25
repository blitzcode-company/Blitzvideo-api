<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Etiqueta;
use App\Models\Video;

class EtiquetaController extends Controller
{

    public function asignarEtiquetas(Request $request, $idVideo)
    {
        try {
            $video = Video::findOrFail($idVideo);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'El video no existe'], 404);
        }
        $etiquetas = $request->input('etiquetas');
        $video->etiquetas()->sync($etiquetas);
        return response()->json(['message' => 'Etiquetas asignadas correctamente al video'], 200);
    }

    public function listarVideosPorEtiqueta(Request $request, $idEtiqueta)
    {
        $etiqueta = Etiqueta::findOrFail($idEtiqueta);
        $videos = $etiqueta->videos()->get();
        return response()->json($videos, 200);
    }

    public function listarEtiquetas(Request $request)
    {
        $etiquetas = Etiqueta::all();
        return response()->json($etiquetas, 200);
    }

    public function filtrarVideosPorEtiquetaYCanal($etiquetaId, $canalId)
    {
        $videos = Video::where('canal_id', $canalId)
            ->whereHas('etiquetas', function ($query) use ($etiquetaId) {
                $query->where('etiquetas.id', $etiquetaId);
            })
            ->get();
        return response()->json($videos, 200);
    }
}