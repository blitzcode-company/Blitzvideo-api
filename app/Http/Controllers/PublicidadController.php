<?php

namespace App\Http\Controllers;

use App\Models\Publicidad;
use App\Models\Visita;
use Illuminate\Http\Request;

class PublicidadController extends Controller
{
    public function crearPublicidad(Request $request)
    {
        $request->validate([
            'empresa' => 'required|string|max:255',
            'prioridad' => 'required|integer|in:1,2,3',
        ]);

        $publicidad = new Publicidad();
        $publicidad->empresa = $request->empresa;
        $publicidad->prioridad = $request->prioridad;
        $publicidad->save();

        return response()->json([
            'mensaje' => 'Publicidad creada exitosamente',
            'publicidad' => $publicidad,
        ], 201);
    }

    public function modificarPublicidad(Request $request, $id)
    {
        $request->validate([
            'empresa' => 'required|string|max:255',
            'prioridad' => 'required|integer|in:1,2,3',
        ]);
        $publicidad = Publicidad::findOrFail($id);
        $publicidad->empresa = $request->empresa;
        $publicidad->prioridad = $request->prioridad;
        $publicidad->save();

        return response()->json([
            'mensaje' => 'Publicidad modificada exitosamente',
            'publicidad' => $publicidad,
        ], 200);
    }

    public function eliminarPublicidad($id)
    {
        $publicidad = Publicidad::findOrFail($id);
        $publicidad->delete();

        return response()->json([
            'mensaje' => 'Publicidad eliminada exitosamente',
        ], 200);
    }

    public function listarPublicidades()
    {
        $publicidades = Publicidad::with('video')->get();
        return response()->json([
            'publicidades' => $publicidades,
        ], 200);
    }

    public function contarVistasPublicidad($publicidadId, $userId)
    {
        $publicidad = Publicidad::findOrFail($publicidadId);
        $videosPublicidad = $publicidad->video()->pluck('video_id')->toArray();
        $cantidadVistas = Visita::where('user_id', $userId)
            ->whereIn('video_id', $videosPublicidad)
            ->count();
        return response()->json([
            'cantidadVistas' => $cantidadVistas,
        ], 200);
    }
}
