<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;


/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/


Broadcast::routes([
    'middleware' => ['auth.api'], 
]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('stream.{id}', function ($user, $id) {
    // Permitir acceso si el usuario está autenticado
    \Log::info('🔐 Autorizando canal stream.' . $id, [
        'user_id' => $user ? $user->id : null,
        'user_name' => $user ? $user->name : null,
    ]);
    
    return $user !== null; // Solo verificar que hay un usuario autenticado
});