<?php

namespace Tests\Feature;

use App\Models\Publicidad;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PublicidadControllerTest extends TestCase
{
    use WithoutMiddleware;
    public function testCrearPublicidad()
    {
        $data = [
            'empresa' => 'Empresa de Prueba',
            'prioridad' => 1,
        ];
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'publicidad', $data);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson([
            'mensaje' => 'Publicidad creada exitosamente',
            'publicidad' => [
                'empresa' => 'Empresa de Prueba',
                'prioridad' => 1,
            ],
        ]);
        $this->assertDatabaseHas('publicidad', $data);
    }

    public function testModificarPublicidad()
    {
        $data = [
            'empresa' => 'Microsoft 2',
            'prioridad' => 3,
        ];

        $response = $this->putJson(env('BLITZVIDEO_BASE_URL') . "publicidad/2", $data);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'mensaje' => 'Publicidad modificada exitosamente',
            'publicidad' => [
                'empresa' => 'Microsoft 2',
                'prioridad' => 3,
            ],
        ]);
        $this->assertDatabaseHas('publicidad', $data);
    }

    public function testEliminarPublicidad()
    {
        $publicidad = Publicidad::create([
            'empresa' => 'Publicidad a Eliminar',
            'prioridad' => 1,
        ]);
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "publicidad/{$publicidad->id}");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['mensaje' => 'Publicidad eliminada exitosamente']);
        $this->assertSoftDeleted('publicidad', [
            'id' => $publicidad->id,
        ]);
    }

    public function testListarPublicidades()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . 'publicidad');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['publicidades' => [['id', 'empresa', 'prioridad']]]);
    }

    public function testContarVistasPublicidad()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "publicidad/1/usuario/2");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['cantidadVistas' => 2]);
    }
}
