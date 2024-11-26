<?php

namespace Tests\Feature;

use App\Models\Stream;
use App\Models\Canal;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class StreamControllerTest extends TestCase
{
    private $baseUrl;

    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = env('BLITZVIDEO_BASE_URL') . 'streams/';
    }

    /** @test */
    public function puede_mostrar_todas_las_transmisiones()
    {
        $response = $this->getJson($this->baseUrl);
        $response->assertStatus(200)->assertJsonStructure([
            '*' => ['id', 'titulo', 'descripcion', 'stream_key', 'activo', 'canal_id', 'created_at', 'updated_at'],
        ]);
    }

    /** @test */
    public function puede_ver_una_transmision_especifica()
    {
        $streamId = 1;
        $response = $this->getJson($this->baseUrl . $streamId);
        $response->assertStatus(200)->assertJsonStructure([
            'transmision' => [
                'id',
                'titulo',
                'descripcion',
                'activo',
                'canal_id',
                'created_at',
                'updated_at',
                'canal' => [
                    'id',
                    'nombre',
                    'user_id',
                ],
            ],
            'url_hls',
        ]);
    }

    /** @test */
    public function puede_guardar_una_nueva_transmision()
    {
        $canalId = 2;
        $canal = Canal::find($canalId);
        $this->assertNotNull($canal, "El canal con ID {$canalId} no existe.");

        $data = [
            'titulo' => 'Nueva Transmisión de Prueba',
            'descripcion' => 'Esta es una descripción de prueba',
        ];

        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", $data);

        $response->assertStatus(201)->assertJson([
            'message' => 'Transmisión creada con éxito.',
            'transmision' => [
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'],
                'activo' => false,
                'canal_id' => $canalId,
            ],
        ]);
    }

    /** @test */
    public function puede_actualizar_datos_de_transmision()
    {
        $canalId = 2;
        $canal = Canal::find($canalId);
        $this->assertNotNull($canal, "El canal con ID {$canalId} no existe.");
        
        $transmision = Stream::where('canal_id', $canalId)->first();
        $this->assertNotNull($transmision, "No se encontró una transmisión asociada al canal con ID {$canalId}.");

        $data = [
            'titulo' => 'Título Actualizado',
            'descripcion' => 'Descripción de transmisión actualizada',
        ];

        $response = $this->putJson(
            $this->baseUrl . "{$transmision->id}/canal/{$canalId}",
            $data
        );

        $response->assertStatus(200)->assertJson([
            'message' => 'Transmisión actualizada con éxito.',
            'transmision' => [
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'],
                'id' => $transmision->id,
            ],
        ]);

        $transmision = $transmision->fresh();
        $this->assertEquals($data['titulo'], $transmision->titulo);
        $this->assertEquals($data['descripcion'], $transmision->descripcion);
    }

    /** @test */
    public function puede_cambiar_el_estado_de_una_transmision()
    {
        $canalId = 2;
        $streamId = 2;

        $response = $this->patchJson($this->baseUrl . "{$streamId}/canal/{$canalId}");

        $response->assertStatus(200)->assertJsonStructure([
            'message', 'transmision' => ['id', 'activo'],
        ]);
    }

    /** @test */
    public function puede_listar_transmision_para_obs()
    {
        $canalId = 2;
        $streamId = 2;

        $response = $this->getJson($this->baseUrl . "{$streamId}/canal/{$canalId}");
        $response->assertStatus(200)->assertJsonStructure([
            'transmision' => ['id', 'titulo', 'descripcion', 'stream_key', 'activo', 'canal_id', 'server'],
        ]);
    }

    /** @test */
    public function puede_eliminar_una_transmision()
    {
        $canalId = 2;
        $streamId = 2;

        $response = $this->deleteJson($this->baseUrl . "{$streamId}/canal/{$canalId}");
        $response->assertStatus(200)->assertJson([
            'message' => 'Transmisión eliminada con éxito.',
        ]);
    }
}
