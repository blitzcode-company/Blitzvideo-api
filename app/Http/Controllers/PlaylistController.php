<?php
namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function crearPlaylist(Request $request)
    {
        $validatedData = $this->validatePlaylistData($request);
        $playlist      = Playlist::create($validatedData);

        if (! empty($validatedData['video_id'])) {
            $playlist->videos()->attach($validatedData['video_id']);
        }
        return $this->successResponse('Playlist creada exitosamente.', $playlist);
    }

    public function agregarVideosAPlaylist(Request $request, $playlistId)
    {
        $validatedData = $this->validateVideoIds($request);
        $playlist      = $this->findPlaylist($playlistId);

        $newVideoIds = $this->filterNewVideoIds($playlist, $validatedData['video_ids']);
        if (empty($newVideoIds)) {
            return $this->errorResponse('Todos los videos ya están en la playlist.', 400);
        }

        $playlist->videos()->attach($newVideoIds);
        return $this->successResponse('Videos agregados exitosamente.', $playlist->load('videos'));
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
        $playlist     = $this->findPlaylist($playlistId);
        $videoId      = $request->query('video_id');
        $fromPlaylist = $request->query('fromPlaylist', false);

        $playlistVideos = $this->filterPlaylistVideos($playlist, $videoId, $fromPlaylist);
        $this->processVideos($playlistVideos);

        return $this->successResponse('Playlist y videos obtenidos exitosamente.', [
            'playlist' => $playlist,
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
        return $playlist->videos()
            ->when($videoId && $fromPlaylist, fn($query) => $query->where('videos.id', '!=', $videoId))
            ->get();
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
