<?php

use App\Http\Controllers\CanalController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\MeGustaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PublicidadController;
use App\Http\Controllers\PuntuaController;
use App\Http\Controllers\ReportaComentarioController;
use App\Http\Controllers\ReportaController;
use App\Http\Controllers\ReportaUsuarioController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\SuscribeController;
use App\Http\Controllers\TransaccionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VisitaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('v1')->group(function () {
    Route::prefix('invitado')->group(function () {
        Route::get('/visita/{videoId}', [VisitaController::class, 'registrarVisitaComoInvitado']);

    });
    Route::prefix('usuario')->group(function () {
        Route::get('/', [UserController::class, 'listarUsuarios']);
        Route::get('/{id}', [UserController::class, 'mostrarUsuarioPorId']);
    });

    Route::prefix('videos')->group(function () {
        Route::get('/', [VideoController::class, 'mostrarTodosLosVideos']);
        Route::get('/{idVideo}', [VideoController::class, 'mostrarInformacionVideo']);
        Route::get('/nombre/{nombre}', [VideoController::class, 'listarVideosPorNombre']);
        Route::get('/tendencia/semana', [VideoController::class, 'listarTendencias']);
        Route::get('/{idVideo}/comentarios', [ComentarioController::class, 'traerComentariosDeVideo']);
        Route::get('/comentarios/{idComentario}/contar-me-gusta', [MeGustaController::class, 'ContadorDeMeGustasPorComentario']);

    });
    Route::prefix('canal')->group(function () {
        Route::get('/{canalId}/videos', [CanalController::class, 'listarVideosDeCanal']);
        Route::get('/usuario', [CanalController::class, 'listarCanales']);
        Route::get('/suscripciones', [SuscribeController::class, 'ListarSuscripciones']);
        Route::get('/usuario/{userId}/suscripciones', [SuscribeController::class, 'ListarSuscripcionesUsuario']);
        Route::get('/{canal_id}/suscripciones/count', [SuscribeController::class, 'ContarSuscripciones']);

    });
    Route::prefix('etiquetas')->group(function () {
        Route::get('/', [EtiquetaController::class, 'listarEtiquetas']);
        Route::get('/{idEtiqueta}/videos', [EtiquetaController::class, 'listarVideosPorEtiqueta']);
        Route::get('/{etiquetaId}/canal/{canalId}/videos', [EtiquetaController::class, 'filtrarVideosPorEtiquetaYCanal']);
    });
    Route::prefix('playlists')->group(function () {
        Route::get('/{userId}/playlists', [PlaylistController::class, 'ListarPlaylistsDeUsuario']);
        Route::get('/{playlistId}/videos', [PlaylistController::class, 'ObtenerPlaylistConVideos']);
    });
    Route::prefix('password')->group(function () {
        Route::post('/email', [PasswordResetController::class, 'enviarRestablecerEnlaceCorreo']);
    });

    Route::prefix('streams')->group(function () {
        Route::get('/', [StreamController::class, 'mostrarTodasLasTransmisiones']);
        Route::get('/{transmisionId}', [StreamController::class, 'verTransmision']);
    });
    Route::prefix('publicidad')->group(function () {
        Route::get('/', [VideoController::class, 'mostrarPublicidad']); 
    });

});

Route::prefix('v1')->middleware('auth.api')->group(function () {
    Route::prefix('usuario')->group(function () {
        Route::get('{userId}/visita/{videoId}', [VisitaController::class, 'registrarVisita']);
        Route::delete('{userId}', [UserController::class, 'darDeBajaUsuario']);
        Route::post('{userId}', [UserController::class, 'editarUsuario']);
    });

    Route::prefix('transaccion')->group(function () {
        Route::post('/plan', [TransaccionController::class, 'registrarPlan']);
        Route::get('/plan/usuario/{user_id}', [TransaccionController::class, 'listarPlan']);
        Route::delete('/plan/usuario/{user_id}', [TransaccionController::class, 'bajaPlan']);
    });
    Route::prefix('videos')->group(function () {
        Route::post('/canal/{idCanal}', [VideoController::class, 'subirVideo']);
        Route::post('/{idVideo}', [VideoController::class, 'editarVideo']);
        Route::delete('/{idVideo}', [VideoController::class, 'bajaLogicaVideo']);
        Route::get('/usuario/{userId}', [VideoController::class, 'listarVideosRecomendados']);
        Route::post('/{idVideo}/comentarios', [ComentarioController::class, 'crearComentario'])->middleware('bloqueo_usuario');
        Route::post('/comentarios/respuesta/{idComentario}', [ComentarioController::class, 'responderComentario'])->middleware('bloqueo_usuario');
        Route::post('/comentarios/{idComentario}', [ComentarioController::class, 'editarComentario'])->middleware('bloqueo_usuario');
        Route::delete('/comentarios/{idComentario}', [ComentarioController::class, 'bajaLogicaComentario'])->middleware('bloqueo_usuario');
        Route::post('/comentarios/{idComentario}/me-gusta', [MeGustaController::class, 'darMeGusta']);
        Route::delete('/comentarios/me-gusta/{idMeGusta}', [MeGustaController::class, 'quitarMeGusta']);
        Route::get('/comentarios/{idComentario}/me-gusta', [MeGustaController::class, 'obtenerEstadoMeGusta']);
        Route::post('/{idVideo}/puntuacion', [PuntuaController::class, 'puntuar']);
        Route::delete('/{idVideo}/puntuacion/', [PuntuaController::class, 'bajaLogicaPuntuacion']);
        Route::get('/{idVideo}/puntuacion/{userId}', [PuntuaController::class, 'obtenerPuntuacionActual']);        
    });

    Route::prefix('playlists')->group(function () {
        Route::post('/', [PlaylistController::class, 'CrearPlaylist']);
        Route::post('/{playlistId}/videos', [PlaylistController::class, 'AgregarVideosAPlaylist']);
        Route::delete('/{playlistId}/videos', [PlaylistController::class, 'QuitarVideoDePlaylist']);
        Route::put('/{playlistId}', [PlaylistController::class, 'ModificarPlaylist']);
        Route::delete('/{playlistId}', [PlaylistController::class, 'BorrarPlaylist']);
    });

    Route::prefix('canal')->group(function () {
        Route::post('/{userId}', [CanalController::class, 'crearCanal']);
        Route::delete('/{canalId}', [CanalController::class, 'darDeBajaCanal']);
        Route::post('/{canalId}/canal', [CanalController::class, 'editarCanal']);
        Route::post('/{canalId}/suscripcion', [SuscribeController::class, 'Suscribirse']);
        Route::delete('/{canalId}/suscripcion', [SuscribeController::class, 'AnularSuscripcion']);
        Route::get('/{canal_id}/usuario/{user_id}/suscripcion', [SuscribeController::class, 'VerificarSuscripcion']);
        Route::delete('/{canal_id}/suscripcion', [SuscribeController::class, 'AnularSuscripcion']);
        Route::put('/{canalId}/usuario/{userId}/notificaciones/activar', [CanalController::class, 'activarNotificaciones']);
        Route::put('/{canalId}/usuario/{userId}/notificaciones/desactivar', [CanalController::class, 'desactivarNotificaciones']);
    });
    Route::prefix('etiquetas')->group(function () {
        Route::post('/videos/{idVideo}', [EtiquetaController::class, 'asignarEtiquetas']);
    });
    Route::prefix('reporte')->group(function () {
        Route::post('/', [ReportaController::class, 'CrearReporte']);
        Route::post('/comentario', [ReportaComentarioController::class, 'CrearReporte']);
        Route::post('/usuario', [ReportaUsuarioController::class, 'CrearReporte']);
    });
    Route::prefix('password')->group(function () {
        Route::post('/reset', [PasswordResetController::class, 'resetPassword'])->name('password.reset');
    });
    Route::prefix('notificacion')->group(function () {
        Route::post('/vista', [NotificacionController::class, 'marcarNotificacionComoVista']);
        Route::get('/usuario/{usuarioId}', [NotificacionController::class, 'listarNotificacionesDelMes']);
        Route::delete('/{notificacionId}/usuario/{usuarioId}', [NotificacionController::class, 'borrarNotificacion']);
        Route::delete('usuario/{usuarioId}', [NotificacionController::class, 'borrarTodasLasNotificaciones']);
    });

    Route::prefix('streams')->group(function () {
        Route::post('/canal/{canalId}', [StreamController::class, 'guardarNuevaTransmision']);
        Route::post('/{transmision}/canal/{canalId}', [StreamController::class, 'actualizarDatosDeTransmision']);
        Route::patch('/{transmision}/canal/{canalId}', [StreamController::class, 'cambiarEstadoDeTransmision']);
        Route::delete('/{transmision}/canal/{canalId}', [StreamController::class, 'eliminarTransmision']);
        Route::get('/{transmision}/canal/{canalId}', [StreamController::class, 'ListarTransmisionOBS']);
        Route::post('/{streamId}/video', [StreamController::class, 'subirVideoDeStream']);
    });

});
