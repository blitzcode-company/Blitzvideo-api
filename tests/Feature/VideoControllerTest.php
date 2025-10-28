<?php
namespace Tests\Feature;

use App\Models\Publicidad;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class VideoControllerTest extends TestCase
{
    use WithoutMiddleware;

    private function expectedVideosJsonStructure()
    {
        return [
            '*' => [
                'id',
                'canal_id',
                'titulo',
                'descripcion',
                'link',
                'miniatura',
                'created_at',
                'updated_at',
                'deleted_at',
                'puntuacion_1',
                'puntuacion_2',
                'puntuacion_3',
                'puntuacion_4',
                'puntuacion_5',
                'visitas_count',
                'promedio_puntuaciones',
                'canal'     => [
                    'id',
                    'user_id',
                    'nombre',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
                'etiquetas' => [
                    '*' => [
                        'id',
                        'nombre',
                        'pivot' => [
                            'video_id',
                            'etiqueta_id',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function expectedVideoJsonStructure()
    {
        return [
            'id',
            'canal_id',
            'titulo',
            'descripcion',
            'link',
            'miniatura',
            'created_at',
            'updated_at',
            'deleted_at',
            'puntuacion_1',
            'puntuacion_2',
            'puntuacion_3',
            'puntuacion_4',
            'puntuacion_5',
            'visitas_count',
            'promedio_puntuaciones',
            'canal'     => [
                'id',
                'nombre',
                'descripcion',
                'user_id',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
            'etiquetas' => [
                '*' => [
                    'id',
                    'nombre',
                    'pivot' => [
                        'video_id',
                        'etiqueta_id',
                    ],
                ],
            ],
        ];
    }
    public function testMostrarTodosLosVideos()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'videos');
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideosJsonStructure());
    }

    public function testListarVideosPorNombre()
    {
        $nombre = "Título";

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'videos/nombre/' . $nombre);
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideosJsonStructure());
    }

    public function testMostrarInformacionVideo()
    {
        $videoId  = Video::first()->id;
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$videoId}");
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideoJsonStructure());
    }

    public function testMostrarInformacionVideoCuandoEstaBloqueado()
    {
        $video            = Video::first();
        $video->bloqueado = true;
        $video->save();
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}");
        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'El video está bloqueado y no se puede acceder.',
            'code'  => 403,
        ]);
    }

    public function testEditarVideo()
    {

        $user  = User::first();
        $video = Video::first();

        $this->actingAs($user);

        $nuevoTitulo      = 'Nuevo título del video';
        $nuevaDescripcion = 'Nueva descripción del video';
        $response         = $this->post(
            env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}",
            [
                'titulo'      => $nuevoTitulo,
                'descripcion' => $nuevaDescripcion,
            ]
        );
        $response->assertStatus(200);
        $this->assertDatabaseHas('videos', [
            'id'          => $video->id,
            'titulo'      => $nuevoTitulo,
            'descripcion' => $nuevaDescripcion,
            'deleted_at'  => $video->deleted_at,
        ]);
    }

    public function testBajaLogicaVideo()
    {
        $video = Video::first();
        if (! $video) {
            $this->assertTrue(false, 'No hay videos en la base de datos para dar de baja.');
        }
        $response = $this->delete(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('videos', ['id' => $video->id]);
    }

    public function testListarVideosRecomendados()
    {
        $user = User::first();
        $this->assertNotNull($user);
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/usuario/{$user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideosJsonStructure());
    }

    public function testListarTendencias()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/tendencia/semana");
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideosJsonStructure());
    }

    public function testListarVideosRelacionados()
    {
        $video = Video::first();
        $this->assertNotNull($video);
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/relacionados");
        $response->assertStatus(200);
        $response->assertJsonStructure($this->expectedVideosJsonStructure());
    }

    public function testMostrarPublicidadConDatosExistentes()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'publicidad');

        $response->assertStatus(200)
            ->assertJsonStructure($this->expectedVideoJsonStructure());
        $publicidad = Publicidad::first();
        $this->assertNotNull($publicidad, 'No se encontraron registros de publicidad.');
    }

    public function testSeleccionPublicidadPorPrioridad()
    {
        $publicidades = Publicidad::orderBy('prioridad', 'asc')->get();

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'publicidad');

        $response->assertStatus(200)
            ->assertJsonStructure($this->expectedVideoJsonStructure());
        $this->assertEquals($publicidades->first()->prioridad, 1, 'La publicidad con mayor prioridad no fue seleccionada.');
    }


}
