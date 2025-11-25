<?php

namespace App\Services;

use App\Events\ViewerStream;
use Illuminate\Support\Facades\Redis;
use App\Models\Canal;

class StreamViewerService
{

    private $ttl = 10;

    public function añadirViewer(int $streamId)

    {
        $key = "stream:{$streamId}:viewers";

        $count = Redis::incr($key);

        broadcast(new ViewerStream($streamId, [
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

        broadcast(new ViewerStream($streamId, [
            'type' => 'viewer_count',
            'count' => $count
        ]));

        return $count;
    }

    public function heartbeat($streamId, $userId)
    {
        $key = "stream:{$streamId}:viewer:{$userId}";

        Redis::setex($key, $this->ttl, true);

        return $this->getCount($streamId);
    }

    public function getCount($streamId)
    {
        return count(Redis::keys("stream:{$streamId}:viewer:*"));
    }
}
