<?php

use App\Http\Controllers\CanalController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\MeGustaController;
use App\Http\Controllers\PuntuaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('v1')->group(function () {
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
});

Route::prefix('v1')->middleware('auth.api')->group(function () {
    Route::prefix('usuario')->group(function () {
        Route::get('{userId}/visita/{videoId}', [UserController::class, 'visita']);
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
        
        Route::post('/{idVideo}/puntuar', [PuntuaController::class, 'puntuar']);
      //  Route::post('/puntuar/{idPuntua}', [PuntuaController::class, 'editarPuntuacion']);
        Route::delete('/puntuar/{idPuntua}', [PuntuaController::class, 'bajaLogicaPuntuacion']);
    });
    Route::prefix('canal')->group(function () {
        Route::post('/{userId}', [CanalController::class, 'crearCanal']);
        Route::delete('/{canalId}', [CanalController::class, 'darDeBajaCanal']);
    });
    Route::prefix('etiquetas')->group(function () {
        Route::post('/videos/{idVideo}', [EtiquetaController::class, 'asignarEtiquetas']);
    });
});