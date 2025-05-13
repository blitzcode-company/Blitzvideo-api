<?php
namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Puntua;
use Illuminate\Http\Request;

class PuntuaController extends Controller
{
    public function puntuar(Request $request, $idVideo)
    {
        $userId = $request->input('user_id');
        $valora = $request->input('valora');
        $this->actualizarPuntuacion($userId, $idVideo, $valora);
        if ($valora === 5) {
            return $this->gestionarFavoritos($userId, $idVideo);
        }
        return response()->json(['message' => 'Puntuación agregada o actualizada exitosamente.'], 200);
    }

    private function actualizarPuntuacion($userId, $idVideo, $valora)
    {
        Puntua::updateOrCreate(
            ['user_id' => $userId, 'video_id' => $idVideo],
            ['valora' => $valora]
        );
    }

    private function gestionarFavoritos($userId, $idVideo)
    {
        $playlistDeFavoritos = Playlist::firstOrCreate(
            ['nombre' => 'Favoritos', 'user_id' => $userId],
            ['acceso' => true]
        );
        if ($playlistDeFavoritos->videos()->where('video_id', $idVideo)->exists()) {
            $playlistDeFavoritos->videos()->detach($idVideo);
            return response()->json(['message' => 'Video eliminado de la playlist "Favoritos".'], 200);
        }
        $playlistDeFavoritos->videos()->attach($idVideo);
        return response()->json(['message' => 'Video agregado a la playlist "Favoritos".'], 200);
    }

    public function obtenerPuntuacionActual($idVideo, $userId)
    {
        $puntua = Puntua::where('user_id', $userId)
            ->where('video_id', $idVideo)
            ->first();
        if (! $puntua) {
            return response()->json(['message' => 'El usuario no ha puntuado este video.'], 404);
        }
        return response()->json([
            'valora'  => $puntua->valora,
            'message' => 'Puntuación actual obtenida exitosamente.',
        ], 200);
    }

    public function bajaLogicaPuntuacion(Request $request, $idVideo)
    {
        $userId = $request->input('user_id');
        $puntua = Puntua::where('user_id', $userId)
            ->where('video_id', $idVideo)
            ->first();
        if (! $puntua) {
            return response()->json(['message' => 'Puntuación no encontrada.'], 404);
        }
        $puntua->delete();
        return response()->json(['message' => 'Puntuación eliminada exitosamente.'], 200);
    }

    public function listarPuntuaciones($idVideo)
    {
        $puntuaciones = Puntua::where('video_id', $idVideo)->get();
        if ($puntuaciones->isEmpty()) {
            return response()->json(['message' => 'No hay puntuaciones para este video.'], 404);
        }
        return response()->json($puntuaciones, 200);
    }

    public function listarPuntuacionesPorUsuario($userId)
    {
        $puntuaciones = Puntua::where('user_id', $userId)->get();
        if ($puntuaciones->isEmpty()) {
            return response()->json(['message' => 'No hay puntuaciones para este usuario.'], 404);
        }
        return response()->json($puntuaciones, 200);
    }
}
