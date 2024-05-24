<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;
use App\Models\User;
use App\Models\Canal;
use App\Http\Controllers\CanalController;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;


class CanalControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testListarVideosDeCanal()
    {
        $canal = Canal::first();
        $controller = new CanalController();
        $response = $controller->listarVideosDeCanal($canal->id);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
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
        $user->name = 'Diego';
        $user->email = 'diego@gmail.com';
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
        $controller = new CanalController();
        $response = $controller->darDeBajaCanal($canalId);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());
        TestResponse::fromBaseResponse($response)
            ->assertJsonFragment(['message' => 'Tu canal y todos tus videos se han eliminado permanentemente']);
    }
}
