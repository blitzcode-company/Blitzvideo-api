<?php

namespace Tests\Feature;

use App\Models\Canal;
use App\Models\Stream;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('s3');
        $file = UploadedFile::fake()->image('miniatura.jpg');

        $data = [
            'titulo' => 'Nueva Transmisión con Miniatura',
            'descripcion' => 'Descripción de prueba con miniatura',
            'miniatura' => $file,
        ];
        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", $data);
        $response->assertStatus(201)->assertJsonStructure([
            'message',
            'transmision' => [
                'id',
                'titulo',
                'descripcion',
                'activo',
                'canal_id',
            ],
        ]);

        $transmisionId = $response->json('transmision.id');
        $this->assertNotNull($transmisionId, "No se devolvió el ID de la transmisión en la respuesta.");
        $transmision = Stream::find($transmisionId);
        $this->assertNotNull($transmision, "No se encontró la transmisión recién creada en la base de datos.");
        $expectedPath = "miniaturas-streams/{$canalId}/{$transmision->id}.jpg";
        Storage::disk('s3')->assertExists($expectedPath);
        $this->assertStringContainsString($expectedPath, $transmision->miniatura);
    }

    /** @test */
    public function puede_actualizar_datos_de_transmision()
    {
        $canalId = 2;
        $canal = Canal::find($canalId);
        $this->assertNotNull($canal, "El canal con ID {$canalId} no existe.");
        $transmision = Stream::where('canal_id', $canalId)->first();
        $this->assertNotNull($transmision, "No se encontró una transmisión asociada al canal con ID {$canalId}.");
        Storage::fake('s3');
        $file = UploadedFile::fake()->image('nueva-miniatura.jpg');

        $data = [
            'titulo' => 'Título Actualizado con Miniatura',
            'descripcion' => 'Descripción actualizada',
            'miniatura' => $file,
        ];

        $response = $this->postJson(
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
        Storage::disk('s3')->assertExists("miniaturas-streams/{$canalId}/{$transmision->id}.jpg");
        $transmision = $transmision->fresh();
        $this->assertEquals($data['titulo'], $transmision->titulo);
        $this->assertEquals($data['descripcion'], $transmision->descripcion);
        $this->assertStringContainsString("miniaturas-streams/{$canalId}/{$transmision->id}.jpg", $transmision->miniatura);
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
