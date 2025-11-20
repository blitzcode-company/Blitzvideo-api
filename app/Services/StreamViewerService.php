<?php

namespace App\Services;

use App\Events\EventoStream;
use Illuminate\Support\Facades\Redis;
use App\Models\Canal;

class StreamViewerService
{
    private function getStreamIdFromKey(string $streamKey)
    {
        $canal = Canal::where('stream_key', $streamKey)->first();
        return $canal?->streamActual()?->id; 
    }
    
   public function añadirViewer(string $streamKey)
    {
        $streamId = $this->getStreamIdFromKey($streamKey);
        if (! $streamId) return 0;

        $count = Redis::incr("stream:{$streamId}:viewers");

        broadcast(new EventoStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count
        ]));

        return $count;
    }


     public function eliminarViewer(string $streamKey)
    {
        $streamId = $this->getStreamIdFromKey($streamKey);
        if (! $streamId) return 0;

        $count = Redis::decr("stream:{$streamId}:viewers");

        if ($count < 0) {
            $count = 0;
            Redis::set("stream:{$streamId}:viewers", 0);
        }

        broadcast(new EventoStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count
        ]));

        return $count;
    }

    public function getCount(string $streamKey)
    {
        $streamId = $this->getStreamIdFromKey($streamKey);
        if (! $streamId) return 0;

        return (int) Redis::get("stream:{$streamId}:viewers") ?? 0;
    }
}
