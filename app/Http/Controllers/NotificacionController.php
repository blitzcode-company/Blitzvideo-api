<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{

    private function crearNotificacion(int $referencia_id, $mensaje, $referencia_tipo)
    {
        return Notificacion::create([
            'mensaje' => $mensaje,
            'referencia_id' => $referencia_id,
            'referencia_tipo' => $referencia_tipo,
        ]);
    }
    public function crearNotificacionDeVideoSubido(int $usuarioId, int $videoId)
    {
        $usuario = $this->obtenerUsuario($usuarioId);
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
    
        $canal = $this->obtenerCanalDelUsuarioConSuscripcionesActivas($usuario);
        if (!$canal) {
            return response()->json(['error' => 'El usuario no tiene un canal con suscripciones activas'], 404);
        }
    
        $mensaje = "¡El canal " . $canal->nombre . " ha subido un nuevo video!";
        $notificacion = $this->crearNotificacion($videoId, $mensaje, 'new_video');
    
        $suscriptoresConNotificacionesActivas = $canal->suscriptores()
            ->wherePivot('notificaciones', 1)
            ->get();
    
        if ($suscriptoresConNotificacionesActivas->isEmpty()) {
            return response()->json([
                'notificacion' => null,
                'suscriptores_notificados' => 0,
            ], 200);
        }
    
        $this->notificarSuscriptores($suscriptoresConNotificacionesActivas, $notificacion);
    
        return response()->json([
            'notificacion' => $notificacion,
            'suscriptores_notificados' => $suscriptoresConNotificacionesActivas->count(),
        ], 201);
    }
    
    private function obtenerUsuario(int $usuarioId)
    {
        return User::find($usuarioId);
    }
    
    private function obtenerCanalDelUsuarioConSuscripcionesActivas(User $usuario)
    {
        return $usuario->canales()
            ->whereHas('suscriptores', function ($query) {
                $query->where('suscribe.notificaciones', 1);
            })
            ->first();
    }
    
    private function notificarSuscriptores($suscriptores, Notificacion $notificacion)
    {
        $suscriptores->each(function ($suscriptor) use ($notificacion) {
            $suscriptor->notificaciones()->attach($notificacion->id, ['leido' => false]);
        });
    }
    

    public function marcarNotificacionComoVista(Request $request)
    {
        $this->validarRequestNotificacionVista($request);
        $notificacion = $this->obtenerNotificacion($request->notificacion_id);
        if (!$notificacion) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
        $usuario = $this->obtenerUsuario($request->usuario_id);
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        if (!$this->usuarioTieneNotificacion($usuario, $request->notificacion_id)) {
            return response()->json(['error' => 'El usuario no tiene esta notificación'], 404);
        }
        $this->marcarComoLeida($usuario, $request->notificacion_id);
        return response()->json([
            'message' => 'Notificación marcada como leída',
            'notificacion' => $notificacion,
        ], 200);
    }

    private function validarRequestNotificacionVista(Request $request)
    {
        $request->validate([
            'usuario_id' => 'required|integer|exists:users,id',
            'notificacion_id' => 'required|integer|exists:notificacion,id',
        ]);
    }

    private function obtenerNotificacion(int $notificacionId)
    {
        return Notificacion::find($notificacionId);
    }

    private function usuarioTieneNotificacion(User $usuario, int $notificacionId)
    {
        return $usuario->notificaciones()->wherePivot('notificacion_id', $notificacionId)->exists();
    }

    private function marcarComoLeida(User $usuario, int $notificacionId)
    {
        $usuario->notificaciones()->updateExistingPivot($notificacionId, ['leido' => true]);
    }

    public function listarNotificacionesDelMes(int $usuarioId)
    {
        $usuario = $this->obtenerUsuario($usuarioId);
        if (!$usuario) {
            return $this->usuarioNoEncontradoResponse();
        }

        $notificaciones = $this->obtenerNotificacionesDelMes($usuario);
        if ($notificaciones->isEmpty()) {
            return $this->noHayNotificacionesResponse();
        }

        return $this->formatearYDevolverNotificaciones($notificaciones);
    }

    private function obtenerNotificacionesDelMes(User $usuario)
    {
        return $usuario->notificaciones()
            ->whereMonth('notificacion.created_at', now()->month)
            ->withPivot('leido')
            ->get();
    }

    private function noHayNotificacionesResponse()
    {
        return response()->json(['message' => 'No hay notificaciones para este mes'], 200);
    }

    private function usuarioNoEncontradoResponse()
    {
        return response()->json(['error' => 'Usuario no encontrado'], 404);
    }

    private function formatearYDevolverNotificaciones($notificaciones)
    {
        $notificacionesData = $notificaciones->map(function ($notificacion) {
            return $this->formatearNotificacion($notificacion);
        });

        return response()->json([
            'notificaciones' => $notificacionesData,
            'total_notificaciones' => $notificaciones->count(),
        ], 200);
    }

    private function formatearNotificacion($notificacion)
    {
        return [
            'id' => $notificacion->id,
            'mensaje' => $notificacion->mensaje,
            'referencia_id' => $notificacion->referencia_id,
            'referencia_tipo' => $notificacion->referencia_tipo,
            'fecha_creacion' => $notificacion->created_at->format('Y-m-d H:i:s'),
            'leido' => $notificacion->pivot->leido,
        ];
    }

    public function borrarNotificacion(int $notificacionId, int $usuarioId)
    {
        $notificacion = $this->obtenerNotificacion($notificacionId);
        if (!$notificacion) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
        $usuario = User::find($usuarioId);
        if (!$usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        $relacion = $usuario->notificaciones()->where('notificacion_id', $notificacionId)->first();

        if (!$relacion) {
            return response()->json(['error' => 'Relación no encontrada'], 404);
        }
        $usuario->notificaciones()->detach($notificacionId);
        if ($notificacion->usuarios()->count() == 0) {
            $notificacion->delete();
        }
        return response()->json(['message' => 'Notificación eliminada con éxito'], 200);
    }

    public function borrarTodasLasNotificaciones(int $usuarioId)
    {
        $usuario = $this->obtenerUsuario($usuarioId);
        if (!$usuario) {
            return $this->usuarioNoEncontradoResponse();
        }
        $this->borrarTodasLasNotificacionesDeUsuario($usuario);
        foreach ($usuario->notificaciones as $notificacion) {
            if ($notificacion->usuarios()->count() == 0) {
                $notificacion->delete();
            }
        }

        return response()->json(['message' => 'Todas las notificaciones eliminadas con éxito'], 200);
    }

    private function borrarTodasLasNotificacionesDeUsuario(User $usuario)
    {

        $usuario->notificaciones()->detach();
    }

    public function crearNotificacionDeComentarioEnVideo(int $videoId, int $usuarioIdComentario)
    {
        $video = Video::with('canal.user')->findOrFail($videoId);
        $usuarioPropietario = $video->canal->user;
        if ($usuarioPropietario->id === $usuarioIdComentario) {
            return $this->respuestaErrorNotificacionComentario('El propietario no recibe notificación de su propio comentario.');
        }
        $mensaje = $this->crearMensajeNotificacionComentario($usuarioIdComentario, $video);
        $notificacion = $this->crearNotificacion($videoId, $mensaje, 'new_comment');
        $usuarioPropietario->notificaciones()->attach($notificacion->id, ['leido' => false]);

        return $this->respuestaExitoNotificacionComentario($notificacion, $usuarioPropietario);
    }

    public function crearNotificacionDeRespuestaComentario(int $usuarioIdComentario, int $usuarioIdRespondedor, int $videoId)
    {
        $video = Video::findOrFail($videoId);
        $usuarioComentario = User::findOrFail($usuarioIdComentario);
        $usuarioRespondedor = User::findOrFail($usuarioIdRespondedor);
        if ($usuarioComentario->id === $usuarioRespondedor->id) {
            return $this->respuestaErrorNotificacionComentario('El usuario no recibe notificación de su propia respuesta.');
        }
        $mensaje = $this->crearMensajeNotificacionRespuesta($usuarioIdComentario, $usuarioIdRespondedor, $video);
        $notificacion = $this->crearNotificacion($videoId, $mensaje, 'new_reply');
        $usuarioComentario->notificaciones()->attach($notificacion->id, ['leido' => false]);

        return $this->respuestaExitoNotificacionComentario($notificacion, $usuarioComentario);
    }

    private function respuestaErrorNotificacionComentario(string $mensaje)
    {
        return response()->json([
            'success' => false,
            'message' => $mensaje,
        ], 200);
    }

    private function respuestaExitoNotificacionComentario($notificacion, $usuario)
    {
        return response()->json([
            'success' => true,
            'message' => 'Notificación creada exitosamente.',
            'notificacion' => [
                'id' => $notificacion->id,
                'mensaje' => $notificacion->mensaje,
                'referencia_id' => $notificacion->referencia_id,
                'referencia_tipo' => $notificacion->referencia_tipo,
                'created_at' => $notificacion->created_at,
            ],
            'usuario' => [
                'id' => $usuario->id,
                'name' => $usuario->name,
            ],
        ], 201);
    }

    private function crearMensajeNotificacionComentario(int $usuarioIdComentario, $video)
    {
        $usuarioComentario = User::findOrFail($usuarioIdComentario);
        return $usuarioComentario->name . " ha comentado en tu video: " . $video->titulo;
    }

    private function crearMensajeNotificacionRespuesta(int $usuarioIdComentario, int $usuarioIdRespondedor, $video)
    {
        $usuarioComentario = User::findOrFail($usuarioIdComentario);
        $usuarioRespondedor = User::findOrFail($usuarioIdRespondedor);
        return $usuarioRespondedor->name . " ha respondido a tu comentario en el video: " . $video->titulo;
    }
}
