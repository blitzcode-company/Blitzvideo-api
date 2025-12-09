<?php
namespace Tests\Unit;

use App\Models\Playlist;
use App\Models\Puntua;
use App\Models\User;
use App\Models\Video;
use App\Models\Canal;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class PlaylistControllerTest extends TestCase
{
    use WithoutMiddleware;

    private $dueno;
    private $otroUsuario;
    private $canal;
    protected function setUp(): void
    {
        parent::setUp();

       $this->dueno = User::create([
            'name'     => 'Dueño ' . uniqid(),
            'email'    => 'dueno_' . uniqid() . '@test.com', 
            'password' => bcrypt('123456'),
        ]);

        $this->otroUsuario = User::create([
            'name'     => 'Otro ' . uniqid(),
            'email'    => 'otro_' . uniqid() . '@test.com', 
            'password' => bcrypt('123456'),
        ]);

        $this->canal = Canal::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Canal de Prueba ' . uniqid(),
        ]);
        
    }

    public function testPuedeCrearPlaylist()
    {
        $user = User::first();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'playlists', [
            'nombre'  => 'Nueva Playlist',
            'acceso'  => true,
            'user_id' => $user->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Playlist creada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'nombre'  => 'Nueva Playlist',
            'user_id' => $user->id,
        ]);
    }

    public function testPuedeAgregarVariosVideosAPlaylist()
    {
        $playlist = Playlist::create([
            'nombre'  => 'Playlist de Prueba',
            'acceso'  => true,
            'user_id' => User::first()->id,
        ]);
        $videos = Video::take(3)->pluck('id')->toArray();

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => $videos,
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Videos agregados exitosamente.']);
        foreach ($videos as $videoId) {
            $this->assertDatabaseHas('video_lista', [
                'playlist_id' => $playlist->id,
                'video_id'    => $videoId,
            ]);
        }
    }

    public function testNoSePuedeAgregarVideosYaExistentesEnPlaylist()
    {
        $playlist = Playlist::create([
            'nombre'  => 'Playlist Existente',
            'acceso'  => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_ids' => [$video->id],
        ]);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJson(['message' => 'Este video ya está en la playlist.']);
    }


    public function testObtenerSiguienteVideoDeLaPlaylist()
    {
        $user = User::first();
    
        $playlist = Playlist::create([
            'nombre' => 'Playlist Siguiente Video',
            'acceso' => true,
            'user_id' => $user->id,
        ]);
    
        $videos = Video::take(3)->get();
    
        foreach ($videos as $index => $video) {
            $playlist->videos()->attach($video->id, ['orden' => $index]);
        }
    
        $primerVideo = $videos[0];
    
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/siguiente/{$primerVideo->id}");
    
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'message' => 'Siguiente video obtenido.',
            'data'    => [
                'id' => $videos[1]->id, 
            ]
        ]);
    }

    public function testNoHaySiguienteVideoSiEsElUltimo()
    {
        $user = User::first();
    
        $playlist = Playlist::create([
            'nombre' => 'Playlist Último Video',
            'acceso' => true,
            'user_id' => $user->id,
        ]);
    
        $videos = Video::take(2)->get();
    
        foreach ($videos as $index => $video) {
            $playlist->videos()->attach($video->id, ['orden' => $index]);
        }
    
        $ultimoVideo = $videos[1];
    
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/siguiente/{$ultimoVideo->id}");
    
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'message' => 'No hay más videos en la playlist.',
            'data'    => null,
        ]);
    }

    public function testPuedeQuitarVideoDePlaylist()
    {
        $playlist = Playlist::create([
            'nombre'  => 'Playlist de Eliminación',
            'acceso'  => true,
            'user_id' => User::first()->id,
        ]);

        $video = Video::first();
        $playlist->videos()->attach($video->id);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}/videos", [
            'video_id' => $video->id,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Video quitado exitosamente.']);
        $this->assertDatabaseMissing('video_lista', [
            'playlist_id' => $playlist->id,
            'video_id'    => $video->id,
        ]);
    }
    
    public function testPuedeObtenerPlaylistConVideos()
    {
        $user     = User::first();
        $playlist = Playlist::create([
            'nombre'  => 'Playlist para Prueba',
            'acceso'  => true,
            'user_id' => $user->id,
        ]);
        $videos = Video::take(3)->get();
        $playlist->videos()->attach($videos->pluck('id'));
        $response = $this->getJson("/api/v1/playlists/{$playlist->id}/videos");
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'data' => [
                'playlist' => [
                    'id',
                    'nombre',
                    'acceso',
                    'user_id',
                    'created_at',
                    'updated_at',
                ],
                'videos'   => [
                    '*' => [
                        'id',
                        'titulo',
                        'descripcion',
                        'miniatura',
                        'link',
                        'canal_id',
                        'duracion',
                        'bloqueado',
                        'acceso',
                        'created_at',
                        'updated_at',
                        'pivot',
                    ],
                ],
            ],
        ]);
    }

    public function testPuedeBorrarPlaylist()
    {
        $playlist = Playlist::create([
            'nombre'  => 'Playlist de Prueba para Borrar',
            'acceso'  => true,
            'user_id' => User::first()->id,
        ]);

        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "playlists/{$playlist->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Playlist borrada exitosamente.']);
        $this->assertDatabaseHas('playlists', [
            'id'         => $playlist->id,
            'deleted_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function testPuedeModificarPlaylist()
    {
        $playlist = Playlist::create([
            'nombre'  => 'Nombre Original',
            'acceso'  => true,
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
            'id'     => $playlist->id,
            'nombre' => 'Nombre de playlist modificado',
            'acceso' => false,
        ]);
    }

    public function testPuedeAgregarVideoAFavoritos()
    {
        $usuario  = User::first();
        $video    = Video::first();
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora'  => 5,
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Video agregado a la playlist "Favoritos".']);
        $this->assertDatabaseHas('video_lista', [
            'playlist_id' => Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first()->id,
            'video_id'    => $video->id,
        ]);
    }

    public function testPuedeEliminarVideoDeFavoritos()
    {
        $usuario = User::first();
        $video   = Video::first();

        Puntua::create([
            'user_id'  => $usuario->id,
            'video_id' => $video->id,
            'valora'   => 5,
        ]);

        $playlist = Playlist::where('nombre', 'Favoritos')->where('user_id', $usuario->id)->first();
        $playlist->videos()->attach($video->id);

        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "videos/{$video->id}/puntuacion", [
            'user_id' => $usuario->id,
            'valora'  => 5,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['message' => 'Video eliminado de la playlist "Favoritos".']);

        $this->assertDatabaseMissing('video_lista', [
            'playlist_id' => $playlist->id,
            'video_id'    => $video->id,
        ]);
    }

    public function testVisitanteSoloVePlaylistsPublicasCreadas()
    {
        $publica = Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Mi Lista Pública',
            'acceso'  => 1, 
        ]);

        Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Mi Lista Privada',
            'acceso'  => 0, 
        ]);

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "playlists/canal/{$this->canal->id}/playlists");

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'playlists.creadas')
                ->assertJsonPath('playlists.creadas.0.id', $publica->id);
    }

    public function testElDuenoVeSusPlaylistsCreadas()
    {
        Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Lista Pública',
            'acceso'  => 1,
        ]);

        Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Lista Privada',
            'acceso'  => 0,
        ]);

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') .  "playlists/canal/{$this->canal->id}/playlists?user_id={$this->dueno->id}");

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'playlists.creadas');
                 
    }
    public function TestOtroUsuarioVeSoloPublicasYSusPrivadasGuardadas()
    {
        $playlistPublicaDueno = Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Pública del Dueño',
            'acceso'  => 1,
        ]);

        Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Privada del Dueño',
            'acceso'  => 0,
        ]);

        $playlistPrivadaOtro = Playlist::create([
            'user_id' => $this->otroUsuario->id,
            'nombre'  => 'Mi Lista Privada',
            'acceso'  => 0,
        ]);

        DB::table('playlist_guardadas')->insert([
            ['user_id' => $this->dueno->id, 'playlist_id' => $playlistPublicaDueno->id, 'orden' => 1],
            ['user_id' => $this->dueno->id, 'playlist_id' => $playlistPrivadaOtro->id, 'orden' => 2],
        ]);

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') .  "/playlists/canal/{$this->canal->id}/playlists?user_id={$this->otroUsuario->id}");

        $response->assertStatus(200);

        $response->assertJsonCount(1, 'playlists.creadas');
        $response->assertJsonFragment(['id' => $playlistPublicaDueno->id]);

        $response->assertJsonCount(2, 'playlists.guardadas');
        $response->assertJsonFragment(['id' => $playlistPublicaDueno->id]);
        $response->assertJsonFragment(['id' => $playlistPrivadaOtro->id]);
    }

 
    public function TestDevuelveMiniaturaYDatosDelAutorCorrectamente()
    {
        $playlist = Playlist::create([
            'user_id' => $this->dueno->id,
            'nombre'  => 'Con Video',
            'acceso'  => 1,
        ]);

        $video = Video::create([
            'canal_id'  => $this->canal->id,
            'titulo'    => 'Primer Video',
            'link'      => 'video_unico_1.mp4',
            'miniatura' => 'miniaturas/prueba.jpg',
            'duracion'  => 300,
        ]);

        DB::table('video_lista')->insert([
            'playlist_id' => $playlist->id,
            'video_id'    => $video->id,
            'orden'       => 1,
        ]);

        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') .  "/playlists/canal/{$this->canal->id}/playlists");

        $response->assertStatus(200)
                 ->assertJsonPath('playlists.creadas.0.miniatura', fn($url) => str_starts_with($url, 'http'))
                 ->assertJsonPath('playlists.creadas.0.autor_nombre', $this->dueno->name)
                 ->assertJsonPath('playlists.creadas.0.total_videos', 1);
    }

    
    public function testDevuelve404SiCanalNoExiste()
    {
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') .  "/playlists/canal/999999/playlists");
        $response->assertStatus(404);
    }
}
