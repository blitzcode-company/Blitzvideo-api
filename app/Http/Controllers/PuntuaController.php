<?php

namespace App\Http\Controllers;

use App\Models\Puntua;
use Illuminate\Http\Request;

class PuntuaController extends Controller
{
    public function puntuar(Request $request, $videoId)
    {
        $this->validarSolicitud($request);

        $userId = $request->input('user_id');
        $valora = $request->input('valora');

        $puntuacionExistente = $this->buscarPuntuacionExistente($userId, $videoId);

        if ($puntuacionExistente) {
            return $this->actualizarPuntuacion($puntuacionExistente, $valora);
        } else {
            return $this->crearPuntuacion($userId, $videoId, $valora);
        }
    }

    private function validarSolicitud(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required|integer|exists:users,id',
            'valora' => 'required|integer|min:1|max:5',
        ]);
    }

    private function buscarPuntuacionExistente($userId, $videoId)
    {
        return Puntua::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->first();
    }

    private function actualizarPuntuacion($puntuacionExistente, $valora)
    {
        $puntuacionExistente->update([
            'valora' => $valora,
        ]);

        return response()->json(['message' => 'Puntuación actualizada exitosamente.'], 200);
    }

    private function crearPuntuacion($userId, $videoId, $valora)
    {
        Puntua::create([
            'user_id' => $userId,
            'video_id' => $videoId,
            'valora' => $valora,
        ]);

        return response()->json(['message' => 'Puntuación agregada exitosamente.'], 201);
    }

    public function bajaLogicaPuntuacion($idPuntua)
    {
        $puntua = Puntua::find($idPuntua);

        if ($puntua) {
            $puntua->delete();
            return response()->json(['message' => 'Puntuación eliminada exitosamente.'], 200);
        }

        return response()->json(['message' => 'Puntuación no encontrada.'], 404);
    }
}
