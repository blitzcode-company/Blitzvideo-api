<?php

namespace App\Http\Controllers;

use App\Models\MeGusta;
use Illuminate\Http\Request;

class MeGustaController extends Controller
{
    public function darMeGusta(Request $request, $idComentario)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
        ]);

        $userId = $request->input('usuario_id');
        $like = MeGusta::firstOrCreate([
            'usuario_id' => $userId,
            'comentario_id' => $idComentario,
        ]);

        if ($like->wasRecentlyCreated) {
            return response()->json(['message' => 'Te ha gustado el comentario.', 'meGustaId' => $like->id], 201);
        } else {
            return response()->json(['message' => 'Ya le has dado Me Gusta a este comentario.'], 422);
        }
    }

    public function quitarMeGusta(Request $request, $idMeGusta)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
        ]);

        $userId = $request->input('usuario_id');
        $like = MeGusta::find($idMeGusta);

        if ($like && $like->usuario_id === $userId) {
            $like->delete();
            return response()->json(['message' => 'Se ha quitado el Me Gusta del comentario.'], 200);
        }

        if ($like) {
            return response()->json(['message' => 'No has dado Me Gusta a este comentario.'], 404);
        }

        return response()->json(['message' => 'Me Gusta no encontrado.'], 404);
    }
    
  

}