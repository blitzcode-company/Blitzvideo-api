<?php

namespace Tests\Unit;

use App\Models\Comentario;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class ComentarioControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeTraerComentariosDeVideo()
    {
        $video = Video::first();
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/comentarios");

        $response->assertStatus(Response::HTTP_OK);
    }

    public function testPuedeCrearUnComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/comentarios", [
            'usuario_id' => $usuario->id,
            'mensaje' => 'Este es un comentario de prueba',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['mensaje' => 'Este es un comentario de prueba']);
        $this->assertDatabaseHas('comentarios', ['mensaje' => 'Este es un comentario de prueba']);
    }

    public function testPuedeResponderAUnComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentarioPadre = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Comentario Padre',
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/respuesta/{$comentarioPadre->id}", [
            'usuario_id' => $usuario->id,
            'mensaje' => 'Respuesta al comentario',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['mensaje' => 'Respuesta al comentario']);
        $this->assertDatabaseHas('comentarios', ['mensaje' => 'Respuesta al comentario', 'respuesta_id' => $comentarioPadre->id]);
    }

    public function testPuedeEditarSuPropioComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Mensaje original',
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/{$comentario->id}", [
            'usuario_id' => $usuario->id,
            'mensaje' => 'Mensaje editado',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Comentario actualizado correctamente.']);
        $this->assertDatabaseHas('comentarios', ['id' => $comentario->id, 'mensaje' => 'Mensaje editado']);
    }

    public function testNoPuedeEditarElComentarioDeOtroUsuario()
    {
        $usuario1 = User::find(1);
        $usuario2 = User::find(2);
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario1->id,
            'mensaje' => 'Mensaje original',
        ]);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/{$comentario->id}", [
            'usuario_id' => $usuario2->id,
            'mensaje' => 'Mensaje editado',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson(['error' => 'No tienes permiso para editar este comentario.']);
    }

    public function testPuedeEliminarSuPropioComentario()
    {
        $usuario = User::first();
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario->id,
            'mensaje' => 'Mensaje a eliminar',
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/{$comentario->id}", [
            'usuario_id' => $usuario->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Comentario dado de baja correctamente.']);
        $this->assertSoftDeleted('comentarios', ['id' => $comentario->id]);
    }

    public function testNoPuedeEliminarElComentarioDeOtroUsuario()
    {
        $usuario1 = User::find(1);
        $usuario2 = User::find(2);
        $video = Video::first();
        $comentario = Comentario::create([
            'video_id' => $video->id,
            'usuario_id' => $usuario1->id,
            'mensaje' => 'Mensaje a eliminar',
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/{$comentario->id}", [
            'usuario_id' => $usuario2->id,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson(['error' => 'No tienes permiso para eliminar este comentario.']);
    }

    public function testDevuelveErrorSiIntentaEliminarUnComentarioInexistente()
    {
        $usuario = User::first();

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "videos/comentarios/999", [
            'usuario_id' => $usuario->id,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $response->assertJson(['error' => 'El comentario no existe.']);
    }
}
