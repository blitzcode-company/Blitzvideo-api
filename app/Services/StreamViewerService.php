<?php

namespace App\Services;

use App\Events\EventoStream;
use Illuminate\Support\Facades\Redis;
use App\Models\Canal;

class StreamViewerService
{
 public function añadirViewer(int $streamId)
    {
        $key = "stream:{$streamId}:viewers";

        $count = Redis::incr($key);

        broadcast(new EventoStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count
        ]));

        return $count;
    }

    public function eliminarViewer(int $streamId)
    {
        $key = "stream:{$streamId}:viewers";

        $count = Redis::decr($key);

        if ($count < 0) {
            $count = 0;
            Redis::set($key, 0);
        }

        broadcast(new EventoStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count
        ]));

        return $count;
    }

    public function getCount(int $streamId)
    {
        return (int) Redis::get("stream:{$streamId}:viewers") ?? 0;
    }
}
