<?php

use App\Http\Controllers\CanalController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\MeGustaController;
use App\Http\Controllers\PuntuaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VisitaController;
use App\Http\Controllers\PlaylistController;
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
    });
    Route::prefix('etiquetas')->group(function () {
        Route::get('/', [EtiquetaController::class, 'listarEtiquetas']);
        Route::get('/{idEtiqueta}/videos', [EtiquetaController::class, 'listarVideosPorEtiqueta']);
        Route::get('/{etiquetaId}/canal/{canalId}/videos', [EtiquetaController::class, 'filtrarVideosPorEtiquetaYCanal']);
    });
    Route::prefix('playlists')->group(function() {
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
        Route::post('/playlist/{id}', [PlaylistController::class, 'CrearPlaylist']);

    });
    Route::prefix('playlists')->group(function() {
        Route::post('/', [PlaylistController::class, 'CrearPlaylist']);
        Route::post('/{playlistId}/videos', [PlaylistController::class, 'AgregarVideosAPlaylist']);
        Route::delete('/{playlistId}/videos', [PlaylistController::class, 'QuitarVideoDePlaylist']);
        Route::put('/{playlistId}', [PlaylistController::class, 'ModificarPlaylist']);
        Route::delete('/{playlistId}', [PlaylistController::class, 'BorrarPlaylist']);
    });
    
    Route::prefix('canal')->group(function () {
        Route::post('/{userId}', [CanalController::class, 'crearCanal']);
        Route::delete('/{canalId}', [CanalController::class, 'darDeBajaCanal']);
    });
    Route::prefix('etiquetas')->group(function () {
        Route::post('/videos/{idVideo}', [EtiquetaController::class, 'asignarEtiquetas']);
    });
});
