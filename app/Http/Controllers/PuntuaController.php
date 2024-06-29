<?php

namespace App\Http\Controllers;

use App\Models\Puntua;
use Illuminate\Http\Request;

class PuntuaController extends Controller
{
    public function puntuar(Request $request, $idVideo)
    {
        $userId = $request->input('user_id');
        $valora = $request->input('valora');
        $puntua = Puntua::updateOrCreate(
            ['user_id' => $userId, 'video_id' => $idVideo],
            ['valora' => $valora]
        );
    
        return response()->json(['message' => 'Puntuación agregada o actualizada exitosamente.'], 200);
    }

    public function obtenerPuntuacionActual($idVideo, $userId)
    {
        $puntua = Puntua::where('user_id', $userId)
                        ->where('video_id', $idVideo)
                        ->first();
        if (!$puntua) {
            return response()->json(['message' => 'El usuario no ha puntuado este video.'], 404);
        }
        return response()->json(['valora' => $puntua->valora, 'message' => 'Puntuación actual obtenida exitosamente.'], 200);
    }

    public function bajaLogicaPuntuacion(Request $request, $idVideo)
    {
        $userId = $request->input('user_id');

        $puntua = Puntua::where('user_id', $userId)
                        ->where('video_id', $idVideo)
                        ->first();

        if (!$puntua) {
            return response()->json(['message' => 'Puntuación no encontrada.'], 404);
        }

        $puntua->delete();

        return response()->json(['message' => 'Puntuación eliminada exitosamente.'], 200);
    }

    private function validarSolicitud(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'valora'  => 'required|integer|min:1|max:5'
        ]);
    }

    private function buscarPuntuacionExistente($userId, $videoId)
    {
        return Puntua::where('user_id', $userId)
                     ->where('video_id', $videoId)
                     ->first();
    }

    private function crearPuntuacion($idVideo, $user_id, $valora)
    {
        Puntua::create([
            'video_id' => $idVideo,
            'user_id' => $user_id,
            'valora' => $valora
        ]);
        return response()->json(['message' => 'Puntuación creada']);
    }

    private function actualizarPuntuacion($puntuacion, $valora)
    {
        $puntuacion->valora = $valora;
        $puntuacion->save();
        return response()->json(['message' => 'Puntuación actualizada']);
    }
}
