<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $fillable = [
        'user_id',
        'video_id',
        'ultima_visualizacion',
        'progreso_segundos',
        'completado',
        'veces_visto',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
