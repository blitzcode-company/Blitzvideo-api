<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;
use App\Models\User;
use App\Models\Canal;
use App\Models\Suscribe;
use App\Http\Controllers\CanalController;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;


class CanalControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testListarCanales()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . 'canal/usuario');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'user_id',
                'nombre',
                'descripcion',
                'portada',
                'deleted_at',
                'created_at',
                'updated_at',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'premium',
                    'foto',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function testListarCanalPorId()
    {
        $canal = Canal::first();
        $this->assertNotNull($canal, 'Canal no encontrado.');
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . 'canal/' . $canal->id . '/videos');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'canal_id',
                'titulo',
                'descripcion',
                'link',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
        ]);
    }

    public function testListarVideosDeCanal()
    {
        $canal = Canal::first();
        $this->assertNotNull($canal, 'No se encontró ningún canal en la base de datos.');
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . 'canal/' . $canal->id . '/videos');
        if ($canal->videos->isEmpty()) {
            $this->markTestSkipped('El canal no tiene videos asociados.');
        }
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'canal_id',
                'titulo',
                'descripcion',
                'link',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
        ]);
    }



    

    public function testCrearCanalUsuarioExistenteConCanal()
    {
        $userWithCanal = User::with('canales')->has('canales')->first();
        if (!$userWithCanal) {
            $this->markTestSkipped('No se encontró un usuario con canal en la base de datos');
        }
        $nombre = 'Nombre del Canal';
        $descripcion = 'Descripción del Canal';
        $portada = UploadedFile::fake()->image('portada.jpg');
        $controller = new CanalController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'portada' => $portada
        ]);
        $response = $controller->crearCanal($request, $userWithCanal->id);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->status());
        $this->assertJson($response->content());
        TestResponse::fromBaseResponse($response)
            ->assertJsonFragment(['message' => 'El usuario ya tiene un canal']);
    }

    public function testCrearCanalUsuarioNuevo()
    {
        $user = new User();
        $user->name = 'Pedro';
        $user->email = 'Pedro@gmail.com';
        $user->password = bcrypt('password');
        $user->save();

        $nombre = 'Canal de Blitzcode';
        $descripcion = 'Descripción del Canal';
        $portada = UploadedFile::fake()->image('portada.jpg');
        $controller = new CanalController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'portada' => $portada
        ]);
        $response = $controller->crearCanal($request, $user->id);
        $this->assertEquals(Response::HTTP_CREATED, $response->status());
        $this->assertDatabaseHas('canals', ['user_id' => $user->id]);
    }

    public function testDarDeBajaCanal()
    {
        $canal = Canal::where('nombre', 'Canal de Blitzcode')->first();
        if (!$canal) {
            $this->markTestSkipped('No se encontró un canal con el nombre especificado en la base de datos');
        }
        $canalId = $canal->id;
        $response = $this->delete(env('BLITZVIDEO_BASE_URL') . "canal/{$canalId}");
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Tu canal y todos tus videos se han dado de baja correctamente']);
    }

    public function testActivarNotificaciones()
    {
        $response = $this->putJson(env('BLITZVIDEO_BASE_URL') . "canal/4/usuario/5/notificacion", [
            'estado' => true
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notificaciones activadas para el canal']);

        $suscripcion = Suscribe::where('canal_id', 4)->where('user_id', 5)->first();
        $this->assertEquals(1, $suscripcion->notificaciones, 'Las notificaciones no fueron activadas correctamente');
    }

    public function testDesactivarNotificaciones()
    {
        $response = $this->putJson(env('BLITZVIDEO_BASE_URL') . "canal/4/usuario/5/notificacion", [
            'estado' => false
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notificaciones desactivadas para el canal']);

        $suscripcion = Suscribe::where('canal_id', 4)->where('user_id', 5)->first();
        $this->assertEquals(0, $suscripcion->notificaciones, 'Las notificaciones no fueron desactivadas correctamente');
    }
    
    public function testConsultarEstadoNotificaciones()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "canal/4/usuario/5/notificacion");

        $response->assertStatus(200);
        $response->assertJson(['notificaciones' => false]);
    }
    
}
