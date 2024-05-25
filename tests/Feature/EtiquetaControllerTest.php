<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;
use App\Models\Etiqueta;
use App\Models\Video;

class EtiquetaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testAsignarEtiquetas()
    {
        $video = Video::create([
            'titulo' => 'Video de prueba',
            'descripcion' => 'Descripción del video de prueba',
            'link' => 'https://Blitzvideo.com/video',
            'canal_id' => 1,
        ]);

        $etiqueta = Etiqueta::create([
            'nombre' => 'Etiqueta de prueba',
        ]);
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "etiquetas/videos/{$video->id}", [
            'etiquetas' => [$etiqueta->id],
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Etiquetas asignadas correctamente al video']);
        $video->refresh();
        $this->assertTrue($video->etiquetas->contains('id', $etiqueta->id));
    }

    public function testListarVideosPorEtiqueta()
    {
        $etiqueta = Etiqueta::first();
        $this->assertNotNull($etiqueta, 'No hay etiquetas en la base de datos para realizar la prueba.');
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "etiquetas/{$etiqueta->id}/videos");
        $response->assertStatus(Response::HTTP_OK);
    }
    public function testListarEtiquetas()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "etiquetas");
        $response->assertStatus(Response::HTTP_OK);
    }


    public function testFiltrarVideosPorEtiquetaYCanal()
    {
        $canalId = 1;
        $etiquetaId = 1; 
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "etiquetas/{$etiquetaId}/canal/{$canalId}/videos");
        $response->assertStatus(Response::HTTP_OK);
    }
    
}
