<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\UserController;

Route::prefix('v1')->middleware('auth.api')->group(function () {
    Route::get('/videos/listar', [VideoController::class, 'mostrarTodosLosVideos']);
    Route::get('/videos/{idVideo}/info', [VideoController::class, 'mostrarInformacionVideo']);
    Route::post('/videos/nuevo/{idCanal}', [VideoController::class, 'subirVideo']);
    Route::delete('/videos/{idVideo}', [VideoController::class, 'bajaLogicaVideo']);
    Route::post('/videos/editar/{idVideo}', [VideoController::class, 'editarVideo']);
    Route::get('/videos/buscar/{nombre}', [VideoController::class, 'listarVideosPorNombre']);

    Route::post('/videos/{idVideo}/etiquetas', [EtiquetaController::class, 'asignarEtiquetas']);
    Route::get('/etiquetas', [EtiquetaController::class, 'listarEtiquetas']);
    Route::get('/etiquetas/{idEtiqueta}/videos', [EtiquetaController::class, 'listarVideosPorEtiqueta']);
    Route::get('/canal/{canalId}/etiqueta/{etiquetaId}/videos', [EtiquetaController::class, 'filtrarVideosPorEtiquetaYCanal']);

    Route::get('/canal/{canalId}/videos', [CanalController::class, 'listarVideosDeCanal']);
    Route::post('/canal/nuevo/{userId}', [CanalController::class, 'crearCanal']);
    Route::delete('/canal/{canalId}', [CanalController::class, 'darDeBajaCanal']);

    Route::get('/users/{userId}/visita/{videoId}', [UserController::class, 'visita']);
});
