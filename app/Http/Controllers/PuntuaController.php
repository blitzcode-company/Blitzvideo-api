<?php

namespace App\Http\Controllers;

use App\Models\Puntua;
use Illuminate\Http\Request;

class PuntuaController extends Controller
{
    public function puntuar(Request $request, $videoId)
    {
        $this->validate($request, [
            'user_id' => 'required|integer|exists:users,id',
            'valora' => 'required|integer|min:1|max:5',
        ]);

        $userId = $request->input('user_id');

        $existingRating = Puntua::where('user_id', $userId)
            ->where('video_id', $videoId)
            ->first();

        if (!$existingRating) {
            Puntua::create([
                'user_id' => $userId,
                'video_id' => $videoId,
                'valora' => $request->input('valora'),
            ]);

            return response()->json(['message' => 'Puntuación agregada exitosamente.'], 201);
        }

        return response()->json(['message' => 'Ya has puntuado este video.'], 422);
    }

    public function editarPuntuacion(Request $request, $idPuntua)
    {
        $this->validate($request, [
            'valora' => 'required|integer|min:1|max:5',
        ]);

        $puntua = Puntua::find($idPuntua);

        if ($puntua) {
            $puntua->update([
                'valora' => $request->input('valora'),
            ]);

            return response()->json(['message' => 'Puntuación actualizada exitosamente.'], 200);
        }

        return response()->json(['message' => 'Puntuación no encontrada.'], 404);
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
