<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    protected $fillable = [
        'user_id',
        'video_id',
        'segundos_vistos',
        'duracion_video',
        'view_valida',
        'completado',
        'ultimo_heartbeat',
    ];
    protected $casts = [
        'ultimo_heartbeat' => 'datetime',
        'view_valida' => 'boolean',
        'completado' => 'boolean',
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
