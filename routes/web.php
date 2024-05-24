<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\CanalController;
use App\Http\Middleware\Autenticacion;
Route::get('/', function () {
    return view('welcome');
});