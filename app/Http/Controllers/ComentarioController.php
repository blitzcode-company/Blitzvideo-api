<?php
namespace App\Http\Controllers;

use App\Models\Comentario;
use Illuminate\Http\Request;

class ComentarioController extends Controller
{
    public function traerComentariosDeVideo($idVideo)
    {
        $comentarios = $this->obtenerComentariosConUsuario($idVideo);
        $this->procesarFotosDeUsuarios($comentarios);
        return response()->json($comentarios, 200);
    }

    private function obtenerComentariosConUsuario($idVideo)
    {
        return Comentario::with(['user:id,name,foto'])
            ->where('video_id', $idVideo)
            ->get();
    }

    private function procesarFotosDeUsuarios($comentarios)
    {
        $host   = str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
        $bucket = env('AWS_BUCKET') . '/';
        $comentarios->each(function ($comentario) use ($host, $bucket) {
            if ($comentario->user && $comentario->user->foto && ! str_starts_with($comentario->user->foto, 'http')) {
                $comentario->user->foto = $this->obtenerUrlArchivo($comentario->user->foto, $host, $bucket);
            }
        });
    }

    private function obtenerUrlArchivo($rutaRelativa, $host, $bucket)
    {
        return $rutaRelativa ? $host . $bucket . $rutaRelativa : null;
    }

    public function crearComentario(Request $request, $idVideo)
    {
        $this->validarComentario($request);
        $comentario = $this->guardarComentario($request, [
            'video_id' => $idVideo,
        ]);
        $this->notificarComentarioEnVideo($idVideo, $request->usuario_id);
        return response()->json($comentario, 201);
    }

    public function responderComentario(Request $request, $idComentario)
    {
        $this->validarComentario($request);
        $comentarioPadre = Comentario::findOrFail($idComentario);
        $comentario      = $this->guardarComentario($request, [
            'video_id'     => $comentarioPadre->video_id,
            'respuesta_id' => $comentarioPadre->id,
        ]);
        $this->notificarRespuestaComentario($comentarioPadre, $comentario);
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
        return response()->json(['message' => 'Comentario actualizado correctamente.'], 200);
    }

    private function validarComentario(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
            'mensaje'    => 'required|string',
        ]);
    }

    private function guardarComentario(Request $request, array $extraData)
    {
        $comentarioData = array_merge([
            'usuario_id' => $request->usuario_id,
            'mensaje'    => $request->mensaje,
        ], $extraData);
        $comentario = new Comentario($comentarioData);
        $comentario->save();
        return $comentario;
    }

    private function notificarComentarioEnVideo($idVideo, $usuarioId)
    {
        $notificacionController = new NotificacionController();
        $notificacionController->crearNotificacionDeComentarioEnVideo($idVideo, $usuarioId);
    }

    private function notificarRespuestaComentario($comentarioPadre, $comentario)
    {
        $notificacionController = new NotificacionController();
        $notificacionController->crearNotificacionDeRespuestaComentario(
            $comentarioPadre->usuario_id,
            $comentario->usuario_id,
            $comentarioPadre->video_id
        );
    }

    public function bajaLogicaComentario(Request $request, $idComentario)
    {
        $this->validarUsuarioId($request);
        $comentario = Comentario::find($idComentario);
        if (! $comentario) {
            return $this->respuestaError('El comentario no existe.', 404);
        }
        if ($comentario->usuario_id !== $request->usuario_id) {
            return $this->respuestaError('No tienes permiso para eliminar este comentario.', 403);
        }
        $comentario->delete();
        return $this->respuestaExito('Comentario dado de baja correctamente.');
    }

    private function validarUsuarioId(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|integer',
        ]);
    }

    private function respuestaError($mensaje, $codigo)
    {
        return response()->json(['error' => $mensaje], $codigo);
    }

    private function respuestaExito($mensaje)
    {
        return response()->json(['message' => $mensaje], 200);
    }
}
