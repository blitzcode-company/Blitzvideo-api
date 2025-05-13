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
            'mensaje'         => $mensaje,
            'referencia_id'   => $referencia_id,
            'referencia_tipo' => $referencia_tipo,
        ]);
    }

    public function crearNotificacionDeVideoSubido(int $usuarioId, int $videoId)
    {
        $usuario = $this->obtenerUsuario($usuarioId);
        if (! $usuario) {
            return $this->usuarioNoEncontradoResponse();
        }
        $canal = $this->obtenerCanalConSuscripcionesActivas($usuario);
        if (! $canal) {
            return $this->respuestaErrorCanalSinSuscripciones();
        }
        $notificacion = $this->crearNotificacionDeNuevoVideo($canal, $videoId);
        $suscriptores = $this->obtenerSuscriptoresConNotificacionesActivas($canal);
        if ($suscriptores->isEmpty()) {
            return $this->respuestaSinSuscriptoresNotificados($notificacion);
        }
        $this->notificarSuscriptores($suscriptores, $notificacion);
        return $this->respuestaSuscriptoresNotificados($notificacion, $suscriptores->count());
    }

    private function obtenerUsuario(int $usuarioId)
    {
        return User::find($usuarioId);
    }

    private function obtenerCanalConSuscripcionesActivas(User $usuario)
    {
        return $usuario->canales()
            ->whereHas('suscriptores', fn($query) => $query->where('suscribe.notificaciones', 1))
            ->first();
    }

    private function crearNotificacionDeNuevoVideo($canal, int $videoId)
    {
        $mensaje = "¡El canal " . $canal->nombre . " ha subido un nuevo video!";
        return $this->crearNotificacion($videoId, $mensaje, 'new_video');
    }

    private function obtenerSuscriptoresConNotificacionesActivas($canal)
    {
        return $canal->suscriptores()
            ->wherePivot('notificaciones', 1)
            ->get();
    }

    private function respuestaErrorCanalSinSuscripciones()
    {
        return response()->json(['error' => 'El usuario no tiene un canal con suscripciones activas'], 404);
    }

    private function respuestaSinSuscriptoresNotificados($notificacion)
    {
        return response()->json([
            'notificacion'             => $notificacion,
            'suscriptores_notificados' => 0,
        ], 200);
    }

    private function notificarSuscriptores($suscriptores, Notificacion $notificacion)
    {
        $suscriptores->each(function ($suscriptor) use ($notificacion) {
            $suscriptor->notificaciones()->attach($notificacion->id, ['leido' => false]);
        });
    }

    private function respuestaSuscriptoresNotificados($notificacion, int $totalSuscriptores)
    {
        return response()->json([
            'notificacion'             => $notificacion,
            'suscriptores_notificados' => $totalSuscriptores,
        ], 201);
    }

    public function marcarNotificacionComoVista(Request $request)
    {
        $validatedData = $this->validarRequestNotificacionVista($request);
        $notificacion  = $this->obtenerNotificacion($validatedData['notificacion_id']);
        if (! $notificacion) {
            return $this->respuestaError('Notificación no encontrada', 404);
        }
        $usuario = $this->obtenerUsuario($validatedData['usuario_id']);
        if (! $usuario) {
            return $this->respuestaError('Usuario no encontrado', 404);
        }
        if (! $this->usuarioTieneNotificacion($usuario, $validatedData['notificacion_id'])) {
            return $this->respuestaError('El usuario no tiene esta notificación', 404);
        }
        $this->marcarComoLeida($usuario, $validatedData['notificacion_id']);
        return $this->respuestaExito('Notificación marcada como leída', $notificacion);
    }

    private function validarRequestNotificacionVista(Request $request): array
    {
        return $request->validate([
            'usuario_id'      => 'required|integer|exists:users,id',
            'notificacion_id' => 'required|integer|exists:notificacion,id',
        ]);
    }

    private function obtenerNotificacion(int $notificacionId): ?Notificacion
    {
        return Notificacion::find($notificacionId);
    }

    private function usuarioTieneNotificacion(User $usuario, int $notificacionId): bool
    {
        return $usuario->notificaciones()->wherePivot('notificacion_id', $notificacionId)->exists();
    }

    private function marcarComoLeida(User $usuario, int $notificacionId): void
    {
        $usuario->notificaciones()->updateExistingPivot($notificacionId, ['leido' => true]);
    }

    private function respuestaError(string $mensaje, int $codigo)
    {
        return response()->json(['error' => $mensaje], $codigo);
    }

    private function respuestaExito(string $mensaje, $notificacion)
    {
        return response()->json([
            'success'      => true, 
            'message'      => $mensaje,
            'notificacion' => $notificacion,
        ], 201);
    }

    public function listarNotificacionesDelMes(int $usuarioId)
    {
        $usuario = $this->obtenerUsuario($usuarioId);
        if (! $usuario) {
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
            'notificaciones'       => $notificacionesData,
            'total_notificaciones' => $notificaciones->count(),
        ], 200);
    }

    private function formatearNotificacion($notificacion)
    {
        return [
            'id'              => $notificacion->id,
            'mensaje'         => $notificacion->mensaje,
            'referencia_id'   => $notificacion->referencia_id,
            'referencia_tipo' => $notificacion->referencia_tipo,
            'fecha_creacion'  => $notificacion->created_at->format('Y-m-d H:i:s'),
            'leido'           => $notificacion->pivot->leido,
        ];
    }

    public function borrarNotificacion(int $notificacionId, int $usuarioId)
    {
        $notificacion = $this->obtenerNotificacion($notificacionId);
        if (! $notificacion) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
        $usuario = User::find($usuarioId);
        if (! $usuario) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        $relacion = $usuario->notificaciones()->where('notificacion_id', $notificacionId)->first();

        if (! $relacion) {
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
        if (! $usuario) {
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
        $video              = Video::with('canal.user')->findOrFail($videoId);
        $usuarioPropietario = $video->canal->user;
        if ($this->esComentarioPropio($usuarioPropietario->id, $usuarioIdComentario)) {
            return $this->respuestaError('El propietario no recibe notificación de su propio comentario.', 200);
        }
        $mensaje      = $this->crearMensajeNotificacionComentario($usuarioIdComentario, $video);
        $notificacion = $this->crearNotificacion($videoId, $mensaje, 'new_comment');
        $this->asociarNotificacionAUsuario($usuarioPropietario, $notificacion);
        return $this->respuestaExito('Notificación creada exitosamente.', $notificacion);
    }

    private function esComentarioPropio(int $propietarioId, int $comentarioId): bool
    {
        return $propietarioId === $comentarioId;
    }

    private function asociarNotificacionAUsuario(User $usuario, Notificacion $notificacion): void
    {
        $usuario->notificaciones()->attach($notificacion->id, ['leido' => false]);
    }

    public function crearNotificacionDeRespuestaComentario(int $usuarioIdComentario, int $usuarioIdRespondedor, int $videoId)
    {
        $video              = Video::findOrFail($videoId);
        $usuarioComentario  = User::findOrFail($usuarioIdComentario);
        $usuarioRespondedor = User::findOrFail($usuarioIdRespondedor);

        if ($this->esRespuestaPropia($usuarioComentario->id, $usuarioRespondedor->id)) {
            return $this->respuestaError('El usuario no recibe notificación de su propia respuesta.', 200);
        }
        $mensaje      = $this->crearMensajeNotificacionRespuesta($usuarioIdComentario, $usuarioIdRespondedor, $video);
        $notificacion = $this->crearNotificacion($videoId, $mensaje, 'new_reply');
        $this->asociarNotificacionAUsuario($usuarioComentario, $notificacion);
        return $this->respuestaNotificacionDeRespuestaCreada('Notificación creada exitosamente.', $notificacion, $usuarioComentario);
    }

    private function esRespuestaPropia(int $usuarioIdComentario, int $usuarioIdRespondedor): bool
    {
        return $usuarioIdComentario === $usuarioIdRespondedor;
    }

    private function respuestaNotificacionDeRespuestaCreada(string $mensaje, $notificacion, $usuario)
    {
        return response()->json([
            'success'      => true,
            'message'      => $mensaje,
            'notificacion' => [
                'id'              => $notificacion->id,
                'mensaje'         => $notificacion->mensaje,
                'referencia_id'   => $notificacion->referencia_id,
                'referencia_tipo' => $notificacion->referencia_tipo,
                'created_at'      => $notificacion->created_at,
            ],
            'usuario'      => [
                'id'   => $usuario->id,
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
        $usuarioRespondedor = User::findOrFail($usuarioIdRespondedor);
        return $usuarioRespondedor->name . " ha respondido a tu comentario en el video: " . $video->titulo;
    }
}
