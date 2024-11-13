<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificacionController;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class NotificacionControllerTest extends TestCase
{
    use WithoutMiddleware;

    /** @test */
    public function debe_crear_notificacion_cuando_se_sube_un_video_y_notificar_a_los_suscriptores()
    {
        $usuario = User::find(2);
        $video = Video::find(5);
        $this->assertNotNull($usuario, 'Usuario con ID 2 no encontrado');
        $this->assertNotNull($video, 'Video con ID 5 no encontrado');
        $controller = new NotificacionController();
        $response = $controller->crearNotificacionDeVideoSubido($usuario->id, $video->id);
        $this->assertEquals(201, $response->getStatusCode());
        $suscriptoresCount = 0;
        foreach ($usuario->canales as $canal) {
            $suscriptoresCount += $canal->suscriptores->count();
        }
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'notificacion' => $response->getData()->notificacion,
                'suscriptores_notificados' => $suscriptoresCount,
            ]),
            $response->getContent()
        );
        $this->assertDatabaseHas('notificacion', [
            'referencia_id' => $video->id,
            'referencia_tipo' => 'video',
        ]);
    }

    /** @test */
    public function debe_devolver_error_si_el_usuario_no_existe()
    {
        $controller = new NotificacionController();
        $response = $controller->crearNotificacionDeVideoSubido(999, 5);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'Usuario no encontrado',
            ]),
            $response->getContent()
        );
    }

    /** @test */
    public function debe_devolver_error_si_el_usuario_no_tiene_canal_asociado()
    {
        $controller = new NotificacionController();
        $usuario = new User();
        $usuario->id = 44444;
        $usuario->name = 'Usuario Test';
        $usuario->email = 'usuario@test.com';
        $usuario->password = bcrypt('password');
        $usuario->save();
        $video = Video::find(5);
        $response = $controller->crearNotificacionDeVideoSubido($usuario->id, $video->id);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'error' => 'El usuario no tiene un canal asociado',
            ]),
            $response->getContent()
        );
        $usuario->delete();
    }

    /** @test */
    public function debe_marcar_notificacion_como_vista_correctamente()
    {
        $notificacion = Notificacion::first();
        $this->assertNotNull($notificacion, 'No se encontró ninguna notificación en la base de datos.');
        $usuario = User::find(2);
        $this->assertNotNull($usuario, 'El usuario con ID 2 no existe.');
        $this->assertTrue($usuario->notificaciones->contains($notificacion), 'El usuario no tiene esta notificación.');
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'notificacion/vista', [
            'usuario_id' => $usuario->id,
            'notificacion_id' => $notificacion->id,
        ]);
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Notificación marcada como leída',
            'notificacion' => $notificacion->toArray(),
        ]);
    }

    public function debe_listar_notificaciones_del_mes()
    {

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "notificacion/usuario/2");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notificaciones' => [
                '*' => [
                    'id',
                    'mensaje',
                    'referencia_id',
                    'referencia_tipo',
                    'fecha_creacion',
                    'leido',
                ],
            ],
            'total_notificaciones',
        ]);
    }

    /** @test */
    public function debe_borrar_notificacion_para_un_usuario_existente()
    {
        $usuario = User::find(2);
        $this->assertNotNull($usuario);
        $notificacion = Notificacion::find(1);
        $this->assertNotNull($notificacion);
        $usuario->notificaciones()->attach($notificacion->id);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/{$notificacion->id}/usuario/{$usuario->id}");
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notificación eliminada con éxito']);
        $this->assertDatabaseMissing('notifica', [
            'usuario_id' => $usuario->id,
            'notificacion_id' => $notificacion->id,
        ]);
    }

/** @test */
    public function debe_retornar_error_si_no_existe_la_notificacion_o_usuario()
    {
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/999/usuario/2");
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Notificación no encontrada']);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/1/usuario/999");
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Usuario no encontrado']);
    }

/** @test */
    public function debe_retornar_error_si_no_existe_relacion()
    {
        $usuario = User::find(2);
        $notificacion = Notificacion::find(1);
        $this->assertNotNull($notificacion);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/{$notificacion->id}/usuario/{$usuario->id}");
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Relación no encontrada']);
    }

/** @test */
    public function debe_borrar_todas_las_notificaciones_de_un_usuario_existente()
    {
        $usuario = User::find(2);
        $this->assertNotNull($usuario);
        $notificacion1 = Notificacion::find(1);
        $notificacion2 = Notificacion::find(2);
        $notificacion3 = Notificacion::find(3);
        $this->assertNotNull($notificacion1);
        $this->assertNotNull($notificacion2);
        $this->assertNotNull($notificacion3);
        $usuario->notificaciones()->attach([$notificacion1->id, $notificacion2->id, $notificacion3->id]);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/usuario/{$usuario->id}");
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Todas las notificaciones eliminadas con éxito']);
        $this->assertDatabaseMissing('notifica', [
            'usuario_id' => $usuario->id,
            'notificacion_id' => $notificacion1->id,
        ]);
        $this->assertDatabaseMissing('notifica', [
            'usuario_id' => $usuario->id,
            'notificacion_id' => $notificacion2->id,
        ]);
        $this->assertDatabaseMissing('notifica', [
            'usuario_id' => $usuario->id,
            'notificacion_id' => $notificacion3->id,
        ]);
    }

/** @test */
    public function debe_retornar_error_si_no_existe_usuario_para_borrar_todas_las_notificaciones()
    {
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "notificacion/usuario/999");
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Usuario no encontrado']);
    }

    /** @test */
    public function debe_devolver_no_hay_notificaciones_si_no_hay_notificaciones()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "notificacion/usuario/2");
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'No hay notificaciones para este mes',
        ]);
    }

}
