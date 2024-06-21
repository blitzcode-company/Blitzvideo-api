<?php

namespace Tests\Unit;

use App\Models\Puntua;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PuntuaControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedePuntuarVideo()
    {
        $usuario = User::first();
        $video = Video::first();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuar", [
            'user_id' => $usuario->id,
            'valora' => 5,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Puntuación agregada exitosamente.']);
        $this->assertDatabaseHas('puntua', ['user_id' => $usuario->id, 'video_id' => $video->id, 'valora' => 5]);
    }

    public function testNoPuedePuntuarVideoDosVeces()
    {
        $usuario = User::first();
        $video = Video::first();

        Puntua::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'valora' => 4,
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuar", [
            'user_id' => $usuario->id,
            'valora' => 5,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJson(['message' => 'Ya has puntuado este video.']);
    }

    public function testPuedeEditarPuntuacion()
    {
        $usuario = User::first();
        $video = Video::first();

        $puntua = Puntua::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'valora' => 3,
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/puntuar/{$puntua->id}", [
            'valora' => 5,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación actualizada exitosamente.']);
        $this->assertDatabaseHas('puntua', ['id' => $puntua->id, 'valora' => 5]);
    }

    public function testPuedeEliminarPuntuacion()
    {
        $usuario = User::first();
        $video = Video::first();

        $puntua = Puntua::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'valora' => 4,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/puntuar/{$puntua->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación eliminada exitosamente.']);
        $this->assertSoftDeleted('puntua', ['id' => $puntua->id]);
    }

    public function testDevuelveErrorSiIntentaEliminarPuntuacionInexistente()
    {
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/puntuar/999");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['message' => 'Puntuación no encontrada.']);
    }
}
