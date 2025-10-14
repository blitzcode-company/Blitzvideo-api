<?php

namespace Tests\Unit;

use App\Models\Mensaje;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class ChatStreamControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeEnviarMensajeAUnStreamActivo()
    {
        $user   = User::first();
        $stream = Stream::first();

        $stream->activo = true;
        $stream->save();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'streams/chat/enviar', [
            'user_id'   => $user->id,
            'message'   => 'Mensaje desde el test',
            'stream_id' => $stream->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['status' => 'Mensaje enviado']);

        $this->assertDatabaseHas('mensajes', [
            'user_id'   => $user->id,
            'stream_id' => $stream->id,
            'mensaje'   => 'Mensaje desde el test',
        ]);
    }

    public function testNoPuedeEnviarMensajeAUnStreamInactivo()
    {
        $user   = User::first();
        $stream = Stream::first();

        $stream->activo = false;
        $stream->save();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'streams/chat/enviar', [
            'user_id'   => $user->id,
            'message'   => 'Mensaje a stream inactivo',
            'stream_id' => $stream->id,
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['error' => 'No se puede enviar mensajes a un stream inactivo']);
    }

    public function testPuedeObtenerMensajesDelStream()
    {
        $user   = User::first();
        $stream = Stream::first();

        $stream->activo = true;
        $stream->save();


        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "streams/chat/mensajes/{$stream->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertCount(1, $response->json());
    }

    public function testNoDevuelveMensajesBloqueados()
    {
        $user   = User::first();
        $stream = Stream::first();

        $stream->activo = true;
        $stream->save();

        Mensaje::create([
            'user_id'   => $user->id,
            'stream_id' => $stream->id,
            'mensaje'   => 'Mensaje bloqueado',
            'bloqueado' => true,
        ]);

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "streams/chat/mensajes/{$stream->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertCount(1, $response->json());
    }
}
