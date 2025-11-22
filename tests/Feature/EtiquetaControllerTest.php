<?php

namespace Tests\Feature;

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
            'miniatura' => 'https://Blitzvideo.com/miniatura',
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

    public function testListarEtiquetas()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "etiquetas");
        $response->assertStatus(Response::HTTP_OK);
    }
    
    public function testListarEtiquetasMasPopulares()
    {
        $etiqueta1 = Etiqueta::create(['nombre' => 'Etiqueta A']);
        $etiqueta2 = Etiqueta::create(['nombre' => 'Etiqueta B']);
    
        $video1 = Video::create([
            'titulo' => 'Video 1',
            'descripcion' => 'Desc 1',
            'link' => 'https://blitzvideo.com/video1',
            'miniatura' => 'https://blitzvideo.com/mini1',
            'canal_id' => 1,
        ]);
        $video2 = Video::create([
            'titulo' => 'Video 2',
            'descripcion' => 'Desc 2',
            'link' => 'https://blitzvideo.com/video2',
            'miniatura' => 'https://blitzvideo.com/mini2',
            'canal_id' => 1,
        ]);
    
        $video1->etiquetas()->attach([$etiqueta1->id, $etiqueta2->id]);
        $video2->etiquetas()->attach([$etiqueta1->id]);
    
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "etiquetas/popular");
    
        $response->assertStatus(Response::HTTP_OK);
    
        $response->assertJsonStructure([
            '*' => ['id', 'nombre', 'count'],
        ]);
    
        $data = $response->json();
    
        foreach ($data as $etiqueta) {
            $this->assertNotEquals('Publicidad', $etiqueta['nombre']);
        }
        $etiqueta1Data = collect($data)->firstWhere('id', $etiqueta1->id);
        $this->assertEquals(2, $etiqueta1Data['count']);
    
        $etiqueta2Data = collect($data)->firstWhere('id', $etiqueta2->id);
        $this->assertEquals(1, $etiqueta2Data['count']);
    }

    public function testFiltrarVideosPorEtiquetaYCanal()
    {
        $canalId = 1;
        $etiquetaId = 1; 
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "etiquetas/{$etiquetaId}/canal/{$canalId}/videos");
        $response->assertStatus(Response::HTTP_OK);
    }
    
}
