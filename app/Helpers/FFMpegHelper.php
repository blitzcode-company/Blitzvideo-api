<?php

namespace App\Helpers;

use FFMpeg\FFMpeg;

class FFMpegHelper
{
    /**
     * 
     *
     * @return \FFMpeg\FFMpeg
     */
    public static function crearFFMpeg()
    {
        return FFMpeg::create([
            'ffmpeg.binaries' => env('FFMPEG_BINARIES'),
            'ffprobe.binaries' => env('FFPROBE_BINARIES'),
        ]);
    }
}
