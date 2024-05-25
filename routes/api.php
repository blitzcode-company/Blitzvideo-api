<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\UserController;

Route::prefix('v1')->middleware('auth.api')->group(function () {

    Route::prefix('videos')->group(function () {
        Route::get('/', [VideoController::class, 'mostrarTodosLosVideos']);
        Route::get('/{idVideo}', [VideoController::class, 'mostrarInformacionVideo']);
        Route::get('/nombre/{nombre}', [VideoController::class, 'listarVideosPorNombre']);
        Route::post('/canal/{idCanal}', [VideoController::class, 'subirVideo']);
        Route::post('/{idVideo}', [VideoController::class, 'editarVideo']);
        Route::delete('/{idVideo}', [VideoController::class, 'bajaLogicaVideo']);
    });

    Route::prefix('canal')->group(function () {
        Route::get('/{canalId}/videos', [CanalController::class, 'listarVideosDeCanal']);
        Route::post('/{userId}', [CanalController::class, 'crearCanal']);
        Route::delete('/{canalId}', [CanalController::class, 'darDeBajaCanal']);
    });

    Route::prefix('etiquetas')->group(function () {
        Route::get('/', [EtiquetaController::class, 'listarEtiquetas']);
        Route::get('/{idEtiqueta}/videos', [EtiquetaController::class, 'listarVideosPorEtiqueta']);
        Route::get('/{etiquetaId}/canal/{canalId}/videos', [EtiquetaController::class, 'filtrarVideosPorEtiquetaYCanal']);
        Route::post('/videos/{idVideo}', [EtiquetaController::class, 'asignarEtiquetas']);
    });

    Route::get('/users/{userId}/visita/{videoId}', [UserController::class, 'visita']);
});
