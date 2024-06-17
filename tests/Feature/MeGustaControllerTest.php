<?php

namespace Tests\Feature;

use App\Models\Comentario;
use App\Models\MeGusta;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class MeGustaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeDarMeGustaAUnComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Comentario de prueba',
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'videos/comentarios/' . $comentario->id . '/me-gusta', [
            'usuario_id' => $usuario->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Te ha gustado el comentario.']);

        $like = MeGusta::where('usuario_id', $usuario->id)
            ->where('comentario_id', $comentario->id)
            ->first();

        $this->assertNotNull($like);
    }

    public function testNoPuedeDarMeGustaUnComentarioYaGustado()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Comentario de prueba',
        ]);

        MeGusta::create([
            'usuario_id' => $usuario->id,
            'comentario_id' => $comentario->id,
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'videos/comentarios/' . $comentario->id . '/me-gusta', [
            'usuario_id' => $usuario->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Ya le has dado Me Gusta a este comentario.']);
    }

    public function testPuedeQuitarMeGustaDeUnComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Comentario de prueba',
        ]);

        $like = MeGusta::create([
            'usuario_id' => $usuario->id,
            'comentario_id' => $comentario->id,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . 'videos/comentarios/me-gusta/' . $like->id, [
            'usuario_id' => $usuario->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Se ha quitado el Me Gusta del comentario.']);

        $like = MeGusta::where('usuario_id', $usuario->id)
            ->where('comentario_id', $comentario->id)
            ->first();

        $this->assertNull($like);
    }

    public function testNoPuedeQuitarMeGustaDeUnComentarioNoGustado()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Comentario de prueba',
        ]);
    
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . 'videos/comentarios/me-gusta/999', [
            'usuario_id' => $usuario->id,
        ]);
    
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'No has dado Me Gusta a este comentario.']);
    }    
}
