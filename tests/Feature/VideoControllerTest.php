<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;
use App\Models\Video;
use App\Models\Canal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VideoControllerTest extends TestCase
{
    use WithoutMiddleware;
    private function expectedVideoJsonStructure()
    {
        return [
            '*' => [
                'id',
                'canal_id',
                'titulo',
                'descripcion',
                'link',
                'created_at',
                'updated_at',
                'deleted_at',
                'visitas_count',
                'canal' => [
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
                        'created_at',
                        'updated_at',
                    ],
                ],
                'etiquetas' => [
                    '*' => [
                        'id',
                        'nombre',
                        'created_at',
                        'updated_at',
                        'pivot' => [
                            'video_id',
                            'etiqueta_id',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testMostrarTodosLosVideos()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'videos/listar');
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideoJsonStructure());
    }

    public function testListarVideosPorNombre()
    {
        $nombre = "Título";

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'videos/buscar/' . $nombre);
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideoJsonStructure());
    }

    public function testMostrarInformacionVideo()
    {
        $videoId = Video::first()->id;
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}/info");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'canal_id',
            'titulo',
            'descripcion',
            'link',
            'created_at',
            'updated_at',
            'deleted_at',
            'visitas_count',
            'canal' => [
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
                    'created_at',
                    'updated_at',
                ],
            ],
            'etiquetas' => [
                '*' => [
                    'id',
                    'nombre',
                    'created_at',
                    'updated_at',
                    'pivot' => [
                        'video_id',
                        'etiqueta_id',
                    ],
                ],
            ],
        ]);
    }

    public function testSubirVideo()
    {
        $canal = Canal::first();
        $titulo = 'Título de video de prueba';
        $descripcion = 'Descripción del video';
        $video = UploadedFile::fake()->create('video.mp4', 10000);
        $response = $this->post(
            env('BLITZVIDEO_BASE_URL') . "videos/nuevo/{$canal->id}",
            [
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'video' => $video,
            ]
        );
        $response->assertStatus(201);
        $this->assertDatabaseHas('videos', [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'canal_id' => $canal->id,
        ]);
    }

    public function testEditarVideo()
    {
        $video = Video::first();
        $nuevoTitulo = 'Nuevo título del video';
        $nuevaDescripcion = 'Nueva descripción del video';
        $response = $this->post(
            env('BLITZVIDEO_BASE_URL') . "videos/editar/{$video->id}",
            [
                'titulo' => $nuevoTitulo,
                'descripcion' => $nuevaDescripcion,
            ]
        );
        $response->assertStatus(200);
        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'titulo' => $nuevoTitulo,
            'descripcion' => $nuevaDescripcion,
            'deleted_at' => $video->deleted_at,
        ]);
    }

    public function testBajaLogicaVideo()
    {
        $video = Video::first();
        if (!$video) {
            $this->assertTrue(false, 'No hay videos en la base de datos para dar de baja.');
        }
        $response = $this->delete(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('videos', ['id' => $video->id]);
    }
}
