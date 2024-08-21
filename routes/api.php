<?php

use App\Http\Controllers\CanalController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\MeGustaController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PuntuaController;
use App\Http\Controllers\ReportaComentarioController;
use App\Http\Controllers\ReportaController;
use App\Http\Controllers\SuscribeController;
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
        Route::get('/{idVideo}/comentarios', [ComentarioController::class, 'traerComentariosDeVideo']);

    });
    Route::prefix('canal')->group(function () {
        Route::get('/{canalId}/videos', [CanalController::class, 'listarVideosDeCanal']);
        Route::get('/usuario', [CanalController::class, 'listarCanales']);
        Route::get('/suscripciones', [SuscribeController::class, 'ListarSuscripciones']);
        Route::get('/usuario/{userId}/suscripciones', [SuscribeController::class, 'ListarSuscripcionesUsuario']);
    });
    Route::prefix('etiquetas')->group(function () {
        Route::get('/', [EtiquetaController::class, 'listarEtiquetas']);
        Route::get('/{idEtiqueta}/videos', [EtiquetaController::class, 'listarVideosPorEtiqueta']);
        Route::get('/{etiquetaId}/canal/{canalId}/videos', [EtiquetaController::class, 'filtrarVideosPorEtiquetaYCanal']);
    });
    Route::prefix('playlists')->group(function () {
        Route::get('/{userId}/playlists', [PlaylistController::class, 'ListarPlaylistsDeUsuario']);
    });
});

Route::prefix('v1')->middleware('auth.api')->group(function () {
    Route::prefix('usuario')->group(function () {
        Route::get('{userId}/visita/{videoId}', [VisitaController::class, 'registrarVisita']);
        Route::delete('{userId}', [UserController::class, 'darDeBajaUsuario']);
        Route::post('{userId}', [UserController::class, 'editarUsuario']);
    });
    Route::prefix('videos')->group(function () {
        Route::post('/canal/{idCanal}', [VideoController::class, 'subirVideo']);
        Route::post('/{idVideo}', [VideoController::class, 'editarVideo']);
        Route::delete('/{idVideo}', [VideoController::class, 'bajaLogicaVideo']);
        Route::post('/{idVideo}/bloquear', [VideoController::class, 'bloquearVideo']);
        Route::post('/{idVideo}/comentarios', [ComentarioController::class, 'crearComentario']);
        Route::post('/comentarios/respuesta/{idComentario}', [ComentarioController::class, 'responderComentario']);
        Route::post('/comentarios/{idComentario}', [ComentarioController::class, 'editarComentario']);
        Route::delete('/comentarios/{idComentario}', [ComentarioController::class, 'bajaLogicaComentario']);
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
        Route::post('/{canal_id}/suscripcion', [SuscribeController::class, 'Suscribirse']);
        Route::delete('/{canal_id}/suscripcion', [SuscribeController::class, 'AnularSuscripcion']);
    });
    Route::prefix('etiquetas')->group(function () {
        Route::post('/videos/{idVideo}', [EtiquetaController::class, 'asignarEtiquetas']);
    });
    Route::prefix('reporte')->group(function () {
        Route::post('/', [ReportaController::class, 'CrearReporte']);
        Route::get('/', [ReportaController::class, 'ListarReportes']);
        Route::get('/video/{videoId}', [ReportaController::class, 'ListarReportesDeVideo']);
        Route::get('/usuario/{userId}', [ReportaController::class, 'ListarReportesDeUsuario']);
        Route::put('/{reporteId}', [ReportaController::class, 'ModificarReporte']);
        Route::delete('/{reporteId}', [ReportaController::class, 'BorrarReporte']);
        Route::delete('/video/{videoId}', [ReportaController::class, 'BorrarReportesDeVideo']);

        Route::post('/comentario', [ReportaComentarioController::class, 'CrearReporte']);
        Route::get('/comentario', [ReportaComentarioController::class, 'ListarReportes']);
        Route::get('/comentario/{comentarioId}', [ReportaComentarioController::class, 'ListarReportesDeComentario']);
        Route::get('/comentario/usuario/{userId}', [ReportaComentarioController::class, 'ListarReportesDeUsuario']);
        Route::put('/{reporteId}/comentario', [ReportaComentarioController::class, 'ModificarReporte']);
        Route::delete('/{reporteId}/comentario', [ReportaComentarioController::class, 'BorrarReporte']);
        Route::delete('/comentario/{comentarioId}', [ReportaComentarioController::class, 'BorrarReportesDeComentario']);
    });
});
