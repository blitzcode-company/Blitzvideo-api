<?php
namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\User;
use App\Models\Canal;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PlaylistController extends Controller
{
    public function crearPlaylist(Request $request)
    {
        $validatedData = $this->validatePlaylistData($request);
        $playlist      = Playlist::create($validatedData);

        if (!empty($validatedData['video_id'])) {
            $videoId = $validatedData['video_id'];
            $playlist->videos()->attach($validatedData['video_id']);
        }
        return $this->successResponse('Playlist creada exitosamente.', $playlist);
    }

    public function agregarVideosAPlaylist(Request $request, $playlistId)
    {
        $validatedData = $this->validateVideoIds($request);
        $playlist      = $this->findPlaylist($playlistId);
    
        $videoIds      = $validatedData['video_ids'];
        $existenIds   = $playlist->videos->pluck('id')->toArray();
        $nuevoVideoIds   = array_diff($videoIds, $existenIds);
        $yaExisten = array_intersect($videoIds, $existenIds);


        if (empty($nuevoVideoIds)) {
            return $this->errorResponse('Este video ya está en la playlist.', 400);
        }
    
        $ultimoOrden = $playlist->videos()->max('video_lista.orden') ?? 0;
        $attachData = [];
        foreach ($nuevoVideoIds as $videoId) {
            $attachData[$videoId] = ['orden' => ++$ultimoOrden];
        }
        
        if (empty($nuevoVideoIds) && !empty($yaExisten)) {
            return $this->errorResponse(
                'Este video ya está en la playlist.',
                400
            );
        }
    
        if (!empty($yaExisten) && !empty($nuevoVideoIds)) {
            $playlist->videos()->attach($nuevoVideoIds);
            return $this->successResponse(
                'Algunos videos ya estaban en la playlist. Se agregaron los nuevos.',
                $playlist->load('videos')
            );
        }
    
        $playlist->videos()->attach($nuevoVideoIds);
        return $this->successResponse(
            'Videos agregados exitosamente.',
            $playlist->load('videos')
        );
    }


    public function playlistsGuardadasPorElCanal(Request $request, $id)
    {
        $canal    = Canal::with('user')->findOrFail($id);
        $propietarioId  = $canal->user_id;
        $viewerId = (int) $request->query('user_id');
        $esDueno  = $viewerId === $propietarioId;

        $formatearPlaylist = function ($playlist) {
            $primerVideo = $playlist->videos()->orderBy('video_lista.orden')->first();

            return [
                'id'           => $playlist->id,
                'nombre'       => $playlist->nombre,
                'autor_nombre' => $playlist->user?->name ?? 'Anónimo',
                'autor_foto' => $playlist
                    ? $this->obtenerUrlArchivo($playlist->user?->foto, $this->obtenerHostMinio(), $this->obtenerBucket())
                    : null,
                'total_videos' => $playlist->total_videos ?? 0,
                'miniatura'    => $primerVideo
                    ? $this->obtenerUrlArchivo($primerVideo->miniatura, $this->obtenerHostMinio(), $this->obtenerBucket())
                    : null,
                'created_at'   => $playlist->created_at,
            ];
        };

        $playlistsCreadas = Playlist::where('user_id', $propietarioId)
            ->when(!$esDueno, fn($q) => $q->where('acceso', 1))
            ->with(['videos'])
            ->withCount('videos as total_videos')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => $formatearPlaylist($p));

        $playlistsGuardadas = User::find($propietarioId)
            ->playlistsGuardadas()
            ->with(['videos', 'user'])
            ->withCount('videos as total_videos')
            ->where(function ($q) use ($viewerId) {
                $q->where('playlists.acceso', 1)                     
                ->orWhere(function ($sq) use ($viewerId) {        
                    $sq->where('playlists.acceso', 0)
                        ->where('playlists.user_id', $viewerId);
                });
            })
            ->orderBy('playlist_guardadas.orden')
            ->get()
            ->map(fn($p) => $formatearPlaylist($p));

        return response()->json([
            'playlists' => [
                'creadas'   => $playlistsCreadas->values(),
                'guardadas' => $playlistsGuardadas->values(),
            ]
        ]);
    }

    private function obtenerHostMinio()
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
    }

    private function obtenerBucket()
    {
        return env('AWS_BUCKET') . '/';
    }

    public function actualizarOrden(Request $request, $playlistId)
    {
        $playlist = Playlist::findOrFail($playlistId);

        $orden = $request->input('orden');

        
        foreach ($orden as $item) {
            $playlist->videos()->updateExistingPivot($item['video_id'], [
                'orden' => $item['orden'] 
            ]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }

    public function guardarPlaylist(Request $request, $playlistId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = $request->input('user_id');
        $user = User::findOrFail($userId);

        $playlist = Playlist::findOrFail($playlistId);

        if ($user->playlistsGuardadas()->where('playlist_id', $playlistId)->exists()) {
            return $this->errorResponse('Ya tienes esta playlist guardada', 400);
        }

        $ultimoOrden = $user->playlistsGuardadas()->max('playlist_guardadas.orden') ?? 0;
        $user->playlistsGuardadas()->attach($playlistId, ['orden' => $ultimoOrden + 1]);

        return response()->json(['message' => 'Playlist guardada en tus listas']);

    }

        public function estaGuardada(Request $request, $playlistId)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $userId = $request->input('user_id');

        $user = User::findOrFail($userId);
        $guardada = $user->playlistsGuardadas()->where('playlist_id', $playlistId)->exists();

        return response()->json(['guardada' => $guardada]);
    }

        public function quitarPlaylistGuardada(Request $request, $playlistId)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);
        $userId = $request->input('user_id');

        $user = User::findOrFail($userId);
        $user->playlistsGuardadas()->detach($playlistId);

        return response()->json(['message' => 'Quitada de tus listas']);
    }

   public function listarPlaylistsGuardadasDelUsuario($userId)
    {
        $user = User::findOrFail($userId);

        $playlists = $user->playlistsGuardadas()
            ->with(['videos' => function ($query) {
                $query->orderBy('video_lista.orden', 'asc');
            }])
            ->where('acceso', 1) 
            ->orderBy('playlist_guardadas.orden', 'asc')
            ->get();

        return response()->json([
            'message' => 'Playlists guardadas obtenidas',
            'data' => [
                'user_id' => $user->id,
                'playlists' => $playlists
            ]
        ]);
    }



    public function obtenerSiguienteVideo($playlistId, $videoId)
    {
        $playlist = $this->findPlaylist($playlistId);
        $videos = $playlist->videos()->orderBy('video_lista.orden')->get();
        $currentIndex = $videos->search(fn($video) => $video->id == $videoId);
    
        if ($currentIndex === false || $currentIndex === $videos->count() - 1) {
            return $this->successResponse('No hay más videos en la playlist.', null);
        }

        $siguienteVideo = $videos[$currentIndex + 1];
    
        $this->processVideos(collect([$siguienteVideo]));
    
        return $this->successResponse('Siguiente video obtenido.', $siguienteVideo);
    }

    public function listarPlaylistsDeUsuario($userId)
    {
        $user      = User::findOrFail($userId);
        $playlists = $user->playlists()->with('videos')->get();

        $this->processPlaylists($playlists);

        return $this->successResponse('Playlists obtenidas exitosamente.', ['playlists' => $playlists]);
    }

    public function obtenerPlaylistConVideos(Request $request, $playlistId)
    {
        
        try {
            $playlist = $this->findPlaylist($playlistId);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Esta playlist no existe.'
            ], 404);
        }

        $viewerId      = (int) $request->query('user_id');
        $propietarioId = $playlist->user_id;

        if ((int) $playlist->acceso === 0 && $viewerId !== $propietarioId) {
            return response()->json([
                'message' => 'No tienes permiso para ver esta playlist privada.'
            ], 403);
        }

        
      
        $videoId      = $request->query('video_id');
        $fromPlaylist = $request->query('fromPlaylist', false);

        $playlistVideos = $this->filterPlaylistVideos($playlist, $videoId, $fromPlaylist);
        $this->processVideos($playlistVideos);

        $playlistData = [
            'id'         => $playlist->id,
            'nombre'     => $playlist->nombre,
            'acceso'     => $playlist->acceso,
            'user_id'    => $playlist->user_id,
            'created_at' => $playlist->created_at,
            'updated_at' => $playlist->updated_at,
        ];

        return $this->successResponse('Playlist y videos obtenidos exitosamente.', [
            'playlist' => $playlistData,
            'videos'   => $playlistVideos,
        ]);
    }


    public function quitarVideoDePlaylist(Request $request, $playlistId)
    {
        $validatedData = $request->validate(['video_id' => 'required|exists:videos,id']);
        $playlist      = $this->findPlaylist($playlistId);

        $playlist->videos()->detach($validatedData['video_id']);
        return $this->successResponse('Video quitado exitosamente.', $playlist->load('videos'));
    }

    public function borrarPlaylist($playlistId)
    {
        $playlist = $this->findPlaylist($playlistId);
        $playlist->videos()->detach();
        $playlist->delete();

        return $this->successResponse('Playlist borrada exitosamente.');
    }

    public function modificarPlaylist(Request $request, $playlistId)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            'acceso' => 'required|boolean',
        ]);

        $playlist = $this->findPlaylist($playlistId);
        $playlist->update($validatedData);

        return $this->successResponse('Playlist modificada exitosamente.', $playlist);
    }

    private function validatePlaylistData(Request $request)
    {
        return $request->validate([
            'nombre'   => 'required|string|max:255',
            'acceso'   => 'required|boolean',
            'user_id'  => 'required|exists:users,id',
            'video_id' => 'nullable|exists:videos,id',
        ]);
    }

    private function validateVideoIds(Request $request)
    {
        return $request->validate([
            'video_ids'   => 'required|array',
            'video_ids.*' => 'exists:videos,id',
        ]);
    }

    private function findPlaylist($playlistId)
    {
        return Playlist::findOrFail($playlistId);
    }

    private function filterNewVideoIds(Playlist $playlist, array $videoIds)
    {
        $existingIds = $playlist->videos->pluck('id')->toArray();
        return array_diff($videoIds, $existingIds);
    }
    private function filterPlaylistVideos(Playlist $playlist, $videoId, $fromPlaylist)
    {
        $query = $playlist->videos()
            ->with('canal.user')
            ->withCount('visitas')
            ->orderBy('video_lista.orden', 'asc');

        if ($videoId && !$fromPlaylist) {
            $query->where('videos.id', '!=', $videoId);
        }

        return $query->get(); 
    }


    private function processPlaylists($playlists)
    {
        $host   = $this->getHost();
        $bucket = $this->getBucket();

        $playlists->each(fn($playlist) => $this->processVideos($playlist->videos, $host, $bucket));
    }

    private function processVideos($videos, $host = null, $bucket = null)
    {
        $host   = $host ?? $this->getHost();
        $bucket = $bucket ?? $this->getBucket();

        $videos->each(function ($video) use ($host, $bucket) {
            $video->miniatura = $this->obtenerUrlArchivo($video->miniatura, $host, $bucket);
            $video->link      = $this->obtenerUrlArchivo($video->link, $host, $bucket);
        });
    }

    private function obtenerUrlArchivo($rutaRelativa, $host, $bucket)
    {
        if (! $rutaRelativa) {
            return null;
        }
        if (str_starts_with($rutaRelativa, $host . $bucket)) {
            return $rutaRelativa;
        }
        if (filter_var($rutaRelativa, FILTER_VALIDATE_URL)) {
            return $rutaRelativa;
        }
        return $host . $bucket . $rutaRelativa;
    }
    

    private function getHost()
    {
        return str_replace('minio', env('BLITZVIDEO_HOST'), env('AWS_ENDPOINT')) . '/';
    }

    private function getBucket()
    {
        return env('AWS_BUCKET') . '/';
    }

    private function successResponse($message, $data = null)
    {
        return response()->json(['message' => $message, 'data' => $data], 200);
    }

    private function errorResponse($message, $statusCode)
    {
        return response()->json(['message' => $message], $statusCode);
    }
}
