<?php

namespace Database\Seeders;

use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificacionSeeder extends Seeder
{
    public function run()
    {
        $notificaciones = [
            ['mensaje' => 'Nuevo video disponible 1', 'referencia_id' => 1, 'referencia_tipo' => 'video'],
            ['mensaje' => 'Nuevo video disponible 2', 'referencia_id' => 3, 'referencia_tipo' => 'video'],
            ['mensaje' => 'Nuevo video disponible 3', 'referencia_id' => 3, 'referencia_tipo' => 'video'],
        ];

        foreach ($notificaciones as $notificacionData) {
            $notificacion = Notificacion::create($notificacionData);
            $usuarios = User::all();
            foreach ($usuarios as $usuario) {
                $notificacion->usuarios()->attach($usuario->id, ['leido' => false]);
            }
        }
    }
}
