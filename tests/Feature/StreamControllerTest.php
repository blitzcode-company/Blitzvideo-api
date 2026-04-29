<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use App\Models\Canal;
use App\Models\User;
use App\Models\Video;
use App\Models\Stream;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StreamControllerTest extends TestCase
{
    use WithoutMiddleware;

    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = env('BLITZVIDEO_BASE_URL') . 'streams/';



        Redis::shouldReceive('keys')->andReturn([]);
        Redis::shouldReceive('get')->andReturn(0);
        Redis::shouldReceive('set')->andReturn(true);
        Redis::shouldReceive('setex')->andReturn(true);
        Redis::shouldReceive('incr')->andReturn(1);
        Redis::shouldReceive('decr')->andReturn(0);
        Redis::shouldReceive('del')->andReturn(true);
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('sadd')->andReturn(1);
        Redis::shouldReceive('srem')->andReturn(1);
        Redis::shouldReceive('zadd')->andReturn(1);
        Redis::shouldReceive('zremrangebyscore')->andReturn(0);
        Redis::shouldReceive('zcard')->andReturn(0);
        Redis::shouldReceive('zscore')->andReturn(null);
    }


    public function testPuedeMostrarTodasLasTransmisiones()
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'stream_programado',
                'max_viewers',
                'total_viewers',
                'activo',
                'viewers',
                'video_id',
                'titulo',
                'descripcion',
                'link',
                'miniatura',
                'duracion',
                'canal' => [
                    'id',
                    'nombre',
                    'user_id',
                    'foto',
                ],
            ],
        ]);
    }

    public function testMostrarTodasLasTransmisionesRetornaArreglo()
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertIsArray($response->json());
    }

    public function testPuedeVerUnaTransmisionEspecifica()
    {
        $transmisionId = 1;
        $response      = $this->getJson($this->baseUrl . $transmisionId);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'id',
            'stream_programado',
            'max_viewers',
            'total_viewers',
            'activo',
            'url_hls',
            'video' => [
                'id',
                'titulo',
                'descripcion',
                'link',
                'miniatura',
                'duracion',
                'etiquetas',
                'created_at',
            ],
            'canal' => [
                'id',
                'nombre',
                'stream_key',
                'user',
            ],
        ]);
    }

    public function testVerTransmisionRetorna404ConIdInexistente()
    {

        $response = $this->getJson($this->baseUrl . '999999');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPuedeListarTransmisionParaOBS()
    {
        $canalId  = 1;
        $userId   = 1; 
        $response = $this->getJson($this->baseUrl . "canal/{$canalId}?user_id={$userId}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'server',
            'stream_key',
        ]);
    }

    public function testListarTransmisionOBSRetorna403SiNoEsDueno()
    {
        $canalId  = 1;
        $response = $this->getJson($this->baseUrl . "canal/{$canalId}?user_id=999");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson(['message' => 'No tienes permiso para acceder a este canal.']);
    }

    public function testListarTransmisionOBSRetorna400SinUserId()
    {
        $canalId  = 1;
        $response = $this->getJson($this->baseUrl . "canal/{$canalId}");

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['message' => 'El user_id es requerido.']);
    }

    public function testListarTransmisionOBSRetorna404ConCanalInexistente()
    {
        $response = $this->getJson($this->baseUrl . 'canal/999999?user_id=1');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }


    public function testPuedeGuardarNuevaTransmision()
    {
        $canalId = 1;
        Storage::fake('s3');

        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", [
            'titulo'      => 'Stream de prueba',
            'descripcion' => 'Descripción del stream de prueba',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Transmisión creada con éxito.']);
        $response->assertJsonStructure([
            'message',
            'transmision' => [
                'id',
                'video_id',
                'activo',
            ],
        ]);
    }

    public function testGuardarTransmisionConMiniaturaUsandoArchivoBinario()
    {
        $canalId = 1;
        Storage::fake('s3');
        $miniatura = \Illuminate\Http\UploadedFile::fake()->create('miniatura.jpg', 100, 'image/jpeg');

        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", [
            'titulo'      => 'Stream con miniatura',
            'descripcion' => 'Descripción con miniatura',
            'miniatura'   => $miniatura,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Transmisión creada con éxito.']);
    }

    public function testGuardarTransmisionConEtiquetas()
    {
        $canalId = 1;
        Storage::fake('s3');

        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", [
            'titulo'      => 'Stream con etiquetas',
            'descripcion' => 'Descripción con etiquetas',
            'etiquetas'   => [1, 2],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Transmisión creada con éxito.']);
    }

    public function testGuardarTransmisionRequiereTitulo()
    {
        $canalId  = 1;
        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", [
            'descripcion' => 'Sin título',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGuardarTransmisionRequiereDescripcion()
    {
        $canalId  = 1;
        $response = $this->postJson($this->baseUrl . "canal/{$canalId}", [
            'titulo' => 'Sin descripción',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGuardarTransmisionRetorna404ConCanalInexistente()
    {
        $response = $this->postJson($this->baseUrl . 'canal/999999', [
            'titulo'      => 'Stream',
            'descripcion' => 'Descripción',
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPuedeActualizarDatosDeTransmision()
    {
        $transmisionId = 1;
        $canalId       = 1;
        Storage::fake('s3');

        $response = $this->postJson($this->baseUrl . "{$transmisionId}/canal/{$canalId}/update", [
            'titulo'      => 'Título actualizado',
            'descripcion' => 'Descripción actualizada',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Metadatos de la Transmisión (Video) actualizados con éxito.']);
        $response->assertJsonStructure([
            'message',
            'transmision',
        ]);
    }

    public function testActualizarTransmisionConMiniaturaUsandoArchivoBinario()
    {
        $transmisionId = 1;
        $canalId       = 1;
        Storage::fake('s3');
        $miniatura = \Illuminate\Http\UploadedFile::fake()->create('nueva-miniatura.jpg', 100, 'image/jpeg');

        $response = $this->postJson($this->baseUrl . "{$transmisionId}/canal/{$canalId}/update", [
            'titulo'      => 'Título con miniatura',
            'descripcion' => 'Descripción con miniatura',
            'miniatura'   => $miniatura,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Metadatos de la Transmisión (Video) actualizados con éxito.']);
    }

    public function testActualizarTransmisionRetorna403SiNoEstaAsociada()
    {
        $response = $this->postJson($this->baseUrl . "1/canal/2/update", [
            'titulo'      => 'Título',
            'descripcion' => 'Descripción',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson(['message' => 'La transmisión no está asociada a este canal o no tienes permiso.']);
    }

    public function testActualizarTransmisionRequiereTitulo()
    {
        $transmisionId = 1;
        $canalId       = 1;

        $response = $this->postJson($this->baseUrl . "{$transmisionId}/canal/{$canalId}/update", [
            'descripcion' => 'Sin título',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testActualizarTransmisionRetorna404ConStreamInexistente()
    {
        $response = $this->postJson($this->baseUrl . "999999/canal/1/update", [
            'titulo'      => 'Título',
            'descripcion' => 'Descripción',
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
    public function testPuedeEliminarTransmision()
    {
        $transmisionId = 1;
        $canalId       = 1;
        Storage::fake('s3');

        $response = $this->deleteJson($this->baseUrl . "{$transmisionId}/canal/{$canalId}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Transmisión, Video y Miniatura eliminados con éxito.']);
    }

    public function testEliminarTransmisionRetorna403SiNoEstaAsociada()
    {
        $userDueno = User::create([
            'name' => 'Dueno',
            'email' => 'dueno@test.com',
            'password' => bcrypt('123456'),
        ]);

        $userAjeno = User::create([
            'name' => 'Ajeno',
            'email' => 'ajeno@test.com',
            'password' => bcrypt('123456'),
        ]);

        $canalDueno = Canal::create([
            'user_id' => $userDueno->id,
            'nombre' => 'Canal del Dueño',
        ]);
        $canalAjeno = Canal::create([
            'user_id' => $userAjeno->id,
            'nombre' => 'Canal del Ajeno',
        ]);

        $video = Video::create([
            'canal_id' => $canalDueno->id,
            'titulo' => 'Video del Stream',
            'descripcion' => 'Descripción del video del stream',    
            'link' => 'http://ejemplo.com/stream-link',
            'miniatura' => 'default_stream_miniatura.png',
        ]);

        $stream = Stream::create([
            'video_id' => $video->id,
            'activo' => false,
        ]);

        $stream->canales()->attach($canalDueno);
        $response = $this->deleteJson($this->baseUrl . "{$stream->id}/canal/{$canalAjeno->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'La transmisión no está asociada a este canal o no tienes permiso.']);
    }

    public function testEliminarTransmisionRetorna404ConStreamInexistente()
    {
        $response = $this->deleteJson($this->baseUrl . "999999/canal/1");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPuedeActivarStream()
    {
        $userDuenoStream = User::create([
            'name' => 'Dueno',
            'email' => 'duenostream@test.com',
            'password' => bcrypt('123456'),
        ]);

        $canal = Canal::create([
            'user_id' => $userDuenoStream->id,
            'nombre' => 'Canal del Dueño del Stream'
        ]);

        $video = Video::create([
            'canal_id' => $canal->id,
            'titulo' => 'Video del Stream',
            'descripcion' => 'Descripción del video del stream',
            'link' => 'http://ejemplo.com/stream-link2',
            'miniatura' => 'default_stream_miniatura.png',
        ]);

        $stream = Stream::create([
            'video_id' => $video->id,
            'activo' => false,
        ]);

        $stream->canales()->attach($canal);

        $response = $this->postJson($this->baseUrl . 'activar', [
            'stream_id' => $stream->id,
            'user_id'   => $userDuenoStream->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function testActivarStreamRetorna400SinParametros()
    {
        $response = $this->postJson($this->baseUrl . 'activar', []);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson([
            'success' => false,
            'message' => 'Se requieren stream_id y user_id.',
        ]);
    }

    public function testActivarStreamRetorna400SinStreamId()
    {
        $response = $this->postJson($this->baseUrl . 'activar', [
            'user_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['success' => false]);
    }

    public function testActivarStreamRetorna400SinUserId()
    {
        $response = $this->postJson($this->baseUrl . 'activar', [
            'stream_id' => 1,
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['success' => false]);
    }

    public function testActivarStreamRetorna403SiNoEsDueno()
    {
        $userDueno = User::create([
            'name' => 'Dueno',
            'email' => 'duenostream2@test.com',
            'password' => bcrypt('123456'),
        ]);

        $userAjeno = User::create([
            'name' => 'Ajeno',
            'email' => 'ajenostream2@test.com',
            'password' => bcrypt('123456'),
        ]);

        $canal = Canal::create([
            'user_id' => $userDueno->id,
            'nombre' => 'Canal del Dueño del Stream'
        ]);

        $video = Video::create([
            'canal_id' => $canal->id,
            'titulo' => 'Video del Stream',
            'descripcion' => 'Descripción del video del stream',
            'link' => 'http://ejemplo.com/stream-link3',
            'miniatura' => 'default_stream_miniatura.png',
        ]);

        $stream = Stream::create([
            'video_id' => $video->id,
            'activo' => false,
        ]);
        $stream->canales()->attach($canal);

        $response = $this->postJson($this->baseUrl . 'activar', [
            'stream_id' => $stream->id,
            'user_id'   => $userAjeno->id,  
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Acceso denegado: El usuario no es el dueño del canal.'
        ]);
    }

    public function testActivarStreamRetorna404ConStreamInexistente()
    {
        $response = $this->postJson($this->baseUrl . 'activar', [
            'stream_id' => 999999,
            'user_id'   => 1,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson([
            'success' => false,
            'message' => 'Stream o Canal no encontrado.',
        ]);
    }

    public function testPuedeDesactivarStream()
    {
      $userDuenoStream = User::create([
            'name' => 'Dueno',
            'email' => 'duenostream5@test.com',
            'password' => bcrypt('123456'),
        ]);

        $canal = Canal::create([
            'user_id' => $userDuenoStream->id,
            'nombre' => 'Canal del Dueño del Stream'
        ]);

        $video = Video::create([
            'canal_id' => $canal->id,
            'titulo' => 'Video del Stream',
            'descripcion' => 'Descripción del video del stream',
            'link' => 'http://ejemplo.com/stream-link4',
            'miniatura' => 'default_stream_miniatura.png',
        ]);

        $stream = Stream::create([
            'video_id' => $video->id,
            'activo' => false,
        ]);
        $stream->canales()->attach($canal);

         $response = $this->postJson($this->baseUrl . 'desactivar', [
             'stream_id' => $stream->id,
             'user_id'   => $userDuenoStream->id,
         ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
        ]);
    }

    public function testDesactivarStreamRetorna400SinParametros()
    {
        $response = $this->postJson($this->baseUrl . 'desactivar', []);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson([
            'success' => false,
            'message' => 'Se requieren stream_id y user_id.',
        ]);
    }

    public function testDesactivarStreamRetorna403SiNoEsDueno()
    {
         $userDueno = User::create([
             'name' => 'Dueno',
             'email' => 'duenostream3@test.com',
             'password' => bcrypt('123456'),
         ]);

        $userAjeno = User::create([
             'name' => 'Ajeno',
             'email' => 'ajenostream3@test.com',
             'password' => bcrypt('123456'),
        ]);

        $canal = Canal::create([
            'user_id' => $userDueno->id,
            'nombre' => 'Canal del Dueño del Stream'
        ]);

        $video = Video::create([
            'canal_id' => $canal->id,
            'titulo' => 'Video del Stream',
            'descripcion' => 'Descripción del video del stream',
            'link' => 'http://ejemplo.com/stream-link5',
            'miniatura' => 'default_stream_miniatura.png',
        ]);

        $stream = Stream::create([
            'video_id' => $video->id,
            'activo' => false,
        ]);
        $stream->canales()->attach($canal);

         $response = $this->postJson($this->baseUrl . 'desactivar', [
             'stream_id' => $stream->id,
             'user_id'   => $userAjeno->id,   
         ]);

         $response->assertStatus(403);
         $response->assertJson([
             'success' => false,
             'message' => 'Acceso denegado: El usuario no es el dueño del canal.'
         ]);

    }

    public function testDesactivarStreamRetorna404ConStreamInexistente()
    {
        $response = $this->postJson($this->baseUrl . 'desactivar', [
            'stream_id' => 999999,
            'user_id'   => 1,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson([
            'success' => false,
            'message' => 'Stream o Canal no encontrado.',
        ]);
    }

    public function testPuedeObtenerStreamActivo()
    {
        $response = $this->getJson($this->baseUrl . 'activo/usuario?user_id=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['stream']);
    }

    public function testObtenerStreamActivoRetornaStreamNullSiNoHay()
    {
        $response = $this->getJson($this->baseUrl . 'activo/usuario?user_id=2');

        $response->assertStatus(Response::HTTP_OK);
        $this->assertArrayHasKey('stream', $response->json());
    }

    public function testObtenerStreamActivoConStreamExistenteRetornaCampos()
    {
        $response = $this->getJson($this->baseUrl . 'activo/usuario?user_id=1');

        $response->assertStatus(Response::HTTP_OK);
        $stream = $response->json('stream');
        if ($stream !== null) {
            $this->assertArrayHasKey('id', $stream);
            $this->assertArrayHasKey('titulo', $stream);
            $this->assertArrayHasKey('estado', $stream);
            $this->assertArrayHasKey('activo', $stream);
        }
    }

    public function testObtenerStreamActivoRetorna422SinUserId()
    {
        $response = $this->getJson($this->baseUrl . 'activo/usuario');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testObtenerStreamActivoRetorna422ConUserIdInexistente()
    {
        $response = $this->getJson($this->baseUrl . 'activo/usuario?user_id=999999');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPuedeEntrarView()
    {
        $streamId = 1;
        $response = $this->postJson($this->baseUrl . "{$streamId}/entrar");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['ok' => true]);
        $response->assertJsonStructure(['ok', 'viewers']);
    }
    public function testPuedeSalirView()
    {
        $streamId = 1;
        $response = $this->postJson($this->baseUrl . "{$streamId}/salir");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['ok' => true]);
        $response->assertJsonStructure(['ok', 'viewers']);
    }

    public function testPuedeObtenerViewers()
    {
        $streamId = 1;
        $response = $this->getJson($this->baseUrl . "{$streamId}/viewers");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['viewers']);
        $this->assertIsInt($response->json('viewers'));
    }

    public function testPuedeEjecutarHeartbeat()
    {
        $streamId = 1;
        $response = $this->getJson($this->baseUrl . "{$streamId}/heartbeat?user_id=1");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['ok' => true]);
    }

    public function testHlsEventOnPlay()
    {
        $response = $this->postJson($this->baseUrl . 'hls-event', [
            'call' => 'on_play',
            'name' => 'test-stream-key',
            'addr' => '127.0.0.1',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'stream',
            'viewers',
            'event',
            'ip',
        ]);
    }

    public function testHlsEventOnPlayDone()
    {
        $response = $this->postJson($this->baseUrl . 'hls-event', [
            'call' => 'on_play_done',
            'name' => 'test-stream-key',
            'addr' => '127.0.0.1',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'stream',
            'viewers',
            'event',
            'ip',
        ]);
    }

    public function testHlsEventRetorna400SinName()
    {
        $response = $this->postJson($this->baseUrl . 'hls-event', [
            'call' => 'on_play',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    public function testHlsEventRetorna400ConEventoInvalido()
    {
        $response = $this->postJson($this->baseUrl . 'hls-event', [
            'call' => 'evento_invalido',
            'name' => 'test-stream-key',
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
    }
}