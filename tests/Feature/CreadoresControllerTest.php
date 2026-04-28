<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class CreadoresControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function baseUrl()
    {
        return env('BLITZVIDEO_BASE_URL');
    }


    public function testPuedeObtenerResumenDelCreador()
    {
        $response = $this->postJson($this->baseUrl() . 'studio', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'totalVistas',
                'totalSuscriptores',
                'videosSubidos',
                'tasa_completitud',
                'tiempo_promedio',
                'videos_destacados' => [
                    '*' => [
                        'id',
                        'titulo',
                        'miniatura',
                        'vistas',
                        'duracion',
                        'totalPuntuaciones',
                        'promedioPuntuacion',
                        'comentarios',
                    ],
                ],
            ],
            'periodo' => [
                'dias',
                'desde',
                'hasta',
            ],
        ]);
    }

    public function testResumenAceptaParametroDias()
    {
        $response = $this->postJson($this->baseUrl() . 'studio', [
            'user_id' => 1,
            'dias'    => 7,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('periodo.dias', 7);
    }

    public function testResumenRetornaErrorConUserIdInexistente()
    {
        $response = $this->postJson($this->baseUrl() . 'studio', [
            'user_id' => 999999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testResumenRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testResumenRechazaDiasInvalidos()
    {
        $response = $this->postJson($this->baseUrl() . 'studio', [
            'user_id' => 1,
            'dias'    => 999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerEstadisticasDeVideo()
    {
        $videoId  = 1;
        $response = $this->postJson($this->baseUrl() . "studio/videos/{$videoId}/estadisticas", [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'titulo',
                'duracion',
                'vistas',
                'tiempo_promedio_segundos',
                'tasa_completitud',
                'totalPuntuaciones',
                'promedioPuntuacion',
                'puntuacionesPorRating' => [1, 2, 3, 4, 5],
                'comentarios',
            ],
        ]);
    }

    public function testEstadisticasVideoRetorna403SiNoEsPropietario()
    {
        $videoId  = 1;
        $response = $this->postJson($this->baseUrl() . "studio/videos/{$videoId}/estadisticas", [
            'user_id' => 2,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson(['error' => 'No autorizado para ver estadísticas de este video']);
    }

    public function testEstadisticasVideoRetorna500ConVideoInexistente()
    {

        $response = $this->postJson($this->baseUrl() . 'studio/videos/999999/estadisticas', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testEstadisticasVideoAceptaFiltroPorDias()
    {
        $videoId  = 1;
        $response = $this->postJson($this->baseUrl() . "studio/videos/{$videoId}/estadisticas", [
            'user_id' => 1,
            'dias'    => 30,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data' => ['vistas']]);
    }

    public function testEstadisticasVideoRequiereUserId()
    {
        $videoId  = 1;
        $response = $this->postJson($this->baseUrl() . "studio/videos/{$videoId}/estadisticas", []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerVistasPorPeriodo()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'periodo',
                'dias',
                'desde',
                'hasta',
                'total_vistas',
                'vistas_por_dia' => [
                    '*' => ['fecha', 'vistas'],
                ],
            ],
        ]);
    }

    public function testVistasPorPeriodoAceptaPeriodoSemana()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', [
            'user_id' => 1,
            'periodo' => 'semana',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.periodo', 'semana');
        $response->assertJsonPath('data.dias', 7);
    }

    public function testVistasPorPeriodoAceptaPeriodoTrimestre()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', [
            'user_id' => 1,
            'periodo' => 'trimestre',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.periodo', 'trimestre');
        $response->assertJsonPath('data.dias', 84);
    }

    public function testVistasPorPeriodoAceptaPeriodoAnio()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', [
            'user_id' => 1,
            'periodo' => 'año',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.periodo', 'año');
        $response->assertJsonPath('data.dias', 365);
    }

    public function testVistasPorPeriodoRechazaPeriodoInvalido()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', [
            'user_id' => 1,
            'periodo' => 'siglo',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testVistasPorPeriodoRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas-periodo', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerVideosTopRendimiento()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/videos-top', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'titulo',
                    'miniatura',
                    'vistas',
                    'duracion',
                    'totalPuntuaciones',
                    'promedioPuntuacion',
                    'comentarios',
                ],
            ],
        ]);
    }

    public function testVideosTopRendimientoAceptaLimitePersonalizado()
    {
        $limite   = 3;
        $response = $this->postJson($this->baseUrl() . 'studio/videos-top', [
            'user_id' => 1,
            'limite'  => $limite,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThanOrEqual($limite, count($response->json('data')));
    }

    public function testVideosTopRendimientoRechazaLimiteMayorA50()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/videos-top', [
            'user_id' => 1,
            'limite'  => 51,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testVideosTopRendimientoRechazaLimiteMenorA1()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/videos-top', [
            'user_id' => 1,
            'limite'  => 0,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testVideosTopRendimientoRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/videos-top', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPuedeObtenerDatosDeAudiencia()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/audiencia', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'totalSuscriptores',
                'nuevosSuscriptores',
                'suscriptoresPeridos',
                'tasaCrecimiento',
                'tasaRetencion',
            ],
        ]);
    }

    public function testAudienciaRetornaValoresNumericos()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/audiencia', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertIsInt($data['totalSuscriptores']);
        $this->assertIsInt($data['nuevosSuscriptores']);
        $this->assertIsInt($data['suscriptoresPeridos']);
        $this->assertIsNumeric($data['tasaCrecimiento']);
        $this->assertIsNumeric($data['tasaRetencion']);
    }

    public function testAudienciaRetornaErrorConUserIdInexistente()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/audiencia', [
            'user_id' => 999999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAudienciaRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/audiencia', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerDatosDeSuscriptores()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/suscriptores', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'totalSuscriptores',
                'suscriptoresHoy',
                'suscriptores7Dias',
            ],
        ]);
    }

    public function testDatosSuscriptoresRetornaEnteros()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/suscriptores', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertIsInt($data['totalSuscriptores']);
        $this->assertIsInt($data['suscriptoresHoy']);
        $this->assertIsInt($data['suscriptores7Dias']);
    }

    public function testDatosSuscriptoresRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/suscriptores', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPuedeObtenerHistorialDeVistas()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'video_id',
                    'video_titulo',
                    'video_miniatura',
                    'usuario_id',
                    'usuario_nombre',
                    'usuario_email',
                    'segundos_vistos',
                    'duracion_video',
                    'porcentaje_completitud',
                    'view_valida',
                    'completado',
                    'fecha',
                    'fecha_actualizacion',
                ],
            ],
            'periodo' => [
                'desde',
                'hasta',
                'periodo',
            ],
        ]);
    }

    public function testHistorialVistasFiltraPorVideoId()
    {
        $videoId  = 1;
        $response = $this->postJson($this->baseUrl() . 'studio/vistas', [
            'user_id'  => 1,
            'video_id' => $videoId,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        foreach ($response->json('data') as $vista) {
            $this->assertEquals($videoId, $vista['video_id']);
        }
    }

    public function testHistorialVistasRespetaLimite()
    {
        $limite   = 5;
        $response = $this->postJson($this->baseUrl() . 'studio/vistas', [
            'user_id' => 1,
            'limite'  => $limite,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertLessThanOrEqual($limite, count($response->json('data')));
    }

    public function testHistorialVistasRechazaLimiteMayorA100()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas', [
            'user_id' => 1,
            'limite'  => 101,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHistorialVistasRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/vistas', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerTiempoPromedioVisualizacion()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tiempo-promedio', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'tiempo_promedio_segundos',
                'tiempo_promedio_minutos',
                'tiempo_total_horas',
            ],
        ]);
    }

    public function testTiempoPromedioRetornaValoresNumericos()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tiempo-promedio', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertIsNumeric($data['tiempo_promedio_segundos']);
        $this->assertIsNumeric($data['tiempo_promedio_minutos']);
        $this->assertIsNumeric($data['tiempo_total_horas']);
    }

    public function testTiempoPromedioRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tiempo-promedio', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }


    public function testPuedeObtenerTasaCompletitud()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tasa-completitud', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'tasa_completitud',
                'videos_completados',
                'total_visitas',
            ],
        ]);
    }

    public function testTasaCompletitudEstaEnRango()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tasa-completitud', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $tasa = $response->json('data.tasa_completitud');
        $this->assertGreaterThanOrEqual(0, $tasa);
        $this->assertLessThanOrEqual(100, $tasa);
    }

    public function testTasaCompletitudRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/tasa-completitud', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPuedeObtenerDatosDeEngagement()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/engagement', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'totalPuntuaciones',
                'promedioPuntuacion',
                'puntuacionesPorRating' => [1, 2, 3, 4, 5],
                'comentarios',
                'puntuacionesRecientes',
                'comentariosRecientes',
            ],
        ]);
    }

    public function testEngagementPromedioPuntuacionEstaEnRango()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/engagement', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $promedio = $response->json('data.promedioPuntuacion');
        $this->assertGreaterThanOrEqual(0, $promedio);
        $this->assertLessThanOrEqual(5, $promedio);
    }

    public function testEngagementRetornaErrorConUserIdInexistente()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/engagement', [
            'user_id' => 999999,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEngagementRequiereUserId()
    {
        $response = $this->postJson($this->baseUrl() . 'studio/engagement', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}