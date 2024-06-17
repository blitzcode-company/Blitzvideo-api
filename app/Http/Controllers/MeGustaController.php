<?php

namespace App\Http\Controllers;

use App\Models\MeGusta;
use Illuminate\Http\Request;

class MeGustaController extends Controller
{
    public function darMeGusta(Request $request, $idComentario)
    {
        $this->validate($request, [
            'usuario_id' => 'required|integer',
        ]);

        $userId = $request->input('usuario_id');
        $existingLike = MeGusta::where('usuario_id', $userId)
            ->where('comentario_id', $idComentario)
            ->first();
        if (!$existingLike) {
            MeGusta::create([
                'usuario_id' => $userId,
                'comentario_id' => $idComentario,
            ]);
            return response()->json(['message' => 'Te ha gustado el comentario.'], 201);
        }
        return response()->json(['message' => 'Ya le has dado Me Gusta a este comentario.'], 422);
    }

    public function quitarMeGusta(Request $request, $idMeGusta)
    {
        $this->validate($request, [
            'usuario_id' => 'required|integer',
        ]);
        $userId = $request->input('usuario_id');
        $like = MeGusta::find($idMeGusta);

        if ($like) {
            if ($like->usuario_id === $userId) {
                $like->delete();
                return response()->json(['message' => 'Se ha quitado el Me Gusta del comentario.'], 200);
            } else {
                return response()->json(['message' => 'No puedes quitar el Me Gusta de otro usuario.'], 403);
            }
        } else {
            return response()->json(['message' => 'No has dado Me Gusta a este comentario.'], 404);
        }
    }
}
