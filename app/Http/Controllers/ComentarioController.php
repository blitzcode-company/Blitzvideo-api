<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use Illuminate\Http\Request;

class ComentarioController extends Controller
{
    public function traerComentariosDeVideo($idVideo)
    {
        $comentarios = Comentario::with([
            'user:id,name,foto'])->where('video_id', $idVideo)->get();
        return response()->json($comentarios, 200);
    }

    public function crearComentario(Request $request, $idVideo)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
            'mensaje' => 'required|string',
        ]);

        $comentario = new Comentario([
            'usuario_id' => $request->usuario_id,
            'video_id' => $idVideo,
            'mensaje' => $request->mensaje,
        ]);
        $comentario->save();
        $notificacionController = new NotificacionController();
        $notificacionController->crearNotificacionDeComentarioEnVideo($idVideo, $request->usuario_id);
        return response()->json($comentario, 201);
    }

    public function responderComentario(Request $request, $idComentario)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
            'mensaje' => 'required|string',
        ]);
        $comentarioPadre = Comentario::findOrFail($idComentario);
        $comentario = new Comentario([
            'usuario_id' => $request->usuario_id,
            'video_id' => $comentarioPadre->video_id,
            'mensaje' => $request->mensaje,
            'respuesta_id' => $comentarioPadre->id,
        ]);
        $comentario->save();
        $notificacionController = new NotificacionController();
        $notificacionController->crearNotificacionDeRespuestaComentario($comentarioPadre->usuario_id, $comentario->usuario_id, $comentarioPadre->video_id);
        return response()->json($comentario, 201);
    }

    public function editarComentario(Request $request, $idComentario)
    {
        $request->validate([
            'mensaje' => 'required|string',
        ]);
        $comentario = Comentario::findOrFail($idComentario);
        if ($comentario->usuario_id !== $request->usuario_id) {
            return response()->json(['error' => 'No tienes permiso para editar este comentario.'], 403);
        }
        $comentario->mensaje = $request->mensaje;
        $comentario->save();
        return response()->json(['message' => 'Comentario actualizado correctamente'], 200);
    }

    public function bajaLogicaComentario(Request $request, $idComentario)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
        ]);
        $comentario = Comentario::where('id', $idComentario)->first();
        
        if (!$comentario) {
            return response()->json(['error' => 'El comentario no existe.'], 404);
        }
        if ($comentario->usuario_id != $request->usuario_id) {
            return response()->json(['error' => 'No tienes permiso para eliminar este comentario.'], 403);
        }
        $comentario->delete();
        return response()->json(['message' => 'Comentario dado de baja correctamente'], 200);
    }
}
