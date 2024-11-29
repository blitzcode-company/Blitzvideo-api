<?php

namespace Tests\Unit;

use App\Models\Playlist;
use App\Models\Puntua;
use App\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Tests\TestCase;

class PlaylistControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testPuedeCrearPlaylist()
    {
        $user = User::first();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'playlists', [
            'nombre' => 'Nueva Playlist',
            'acceso' => true,
            'user_id' => $user->id,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJson(['message' => 'Playlist creada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'nombre' => 'Nueva Playlist',
            'user_id' => $user->id,
        ]);
    }

    public function testPuedeAgregarVariosVideosAPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Prueba',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $videos = Video::take(3)->pluck('id')->toArray();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => $videos,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['mensaje' => 'Videos agregados a la playlist exitosamente.']);
        foreach ($videos as $videoId) {
            $this->assertDatabaseHas('video_lista', [
                'playlist_id' => $playlist->id,
                'video_id' => $videoId,
            ]);
        }
    }

    public function testNoSePuedeAgregarVideosYaExistentesEnPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist Existente',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => [$video->id],
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['mensaje' => 'Todos los videos ya están en la playlist.']);
    }

    public function testPuedeQuitarVideoDePlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Eliminación',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_id' => $video->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Video quitado de la playlist exitosamente.']);
        $this->assertDatabaseMissing('video_lista', [
            'playlist_id' => $playlist->id,
            'video_id' => $video->id,
        ]);
    }
    public function testPuedeObtenerPlaylistConVideos()
    {
        $user = User::first();
    
        $playlist = Playlist::create([
            'nombre' => 'Playlist para Prueba',
            'acceso' => true,
            'user_id' => $user->id,
        ]);
        $videos = Video::take(3)->get(); 
        $playlist->videos()->attach($videos->pluck('id')); 

        $response = $this->getJson("/api/v1/playlists/{$playlist->id}/videos");
    
        $response->assertStatus(Response::HTTP_OK);
    
        $response->assertJson([
            'playlist' => [
                'id' => $playlist->id,
                'nombre' => $playlist->nombre,
                'acceso' => $playlist->acceso,
                'user_id' => $playlist->user_id,
            ],
            'videos' => $videos->map(function ($video) {
                return [
                    'id' => $video->id,
                    'titulo' => $video->titulo,
                    'descripcion' => $video->descripcion,
                    'miniatura' => $video->miniatura,
                    'link' => $video->link,
                ];
            })->toArray(),
        ]);
    }
    



    public function testPuedeBorrarPlaylist()
    {
        $playlist = Playlist::create([
            'nombre' => 'Playlist de Prueba para Borrar',
            'acceso' => true,
            'user_id' => User::first()->id,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Playlist borrada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'id' => $playlist->id,
            'deleted_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testPuedeModificarPlaylist()
    {
    $playlist = Playlist::create([
        'nombre' => 'Nombre Original',
        'acceso' => true,
        'user_id' => User::first()->id,
    ]);

    $datosModificados = [
        'nombre' => 'Nombre de playlist modificado',  
        'acceso' => false,
    ];

    $response = $this->putJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}", $datosModificados);

    $response->assertStatus(Response::HTTP_OK);
    $response->assertJson(['message' => 'Playlist modificada exitosamente.']);

    $this->assertDatabaseHas('playlists', [
        'id' => $playlist->id,
        'nombre' => 'Nombre de playlist modificado', 
        'acceso' => false, 
    ]);
    }

    public function testPuedeAgregarVideoAFavoritos()
    {
        $usuario = User::first();
        $video = Video::first();
    
        Puntua::create([
            'user_id' => $usuario->id,
            'video_id' => $video->id,
            'valora' => 4,
        ]);
        
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora' => 5,
        ]);
    
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Puntuación agregada o actualizada exitosamente.']);
    
        $this->assertDatabaseHas('video_lista', [
            'playlist_id' => Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first()->id,
            'video_id' => $video->id,
        ]);
    }

    public function testPuedeEliminarVideoDeFavoritos()
{
    $usuario = User::first();
    $video = Video::first();

    Puntua::create([
        'user_id' => $usuario->id,
        'video_id' => $video->id,
        'valora' => 5,
    ]);

    $playlist = Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first();
    $playlist->videos()->attach($video->id);

    $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
        'user_id' => $usuario->id,
        'valora' => 5,
    ]);

    $response->assertStatus(Response::HTTP_OK);
    $response->assertJson(['message' => 'Video eliminado de la playlist "Favoritos".']);

    $this->assertDatabaseMissing('video_lista', [
        'playlist_id' => $playlist->id,
        'video_id' => $video->id,
    ]);
}
}
