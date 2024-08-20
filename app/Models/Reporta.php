<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reporta extends Model
{
    use HasFactory, SoftDeletes; 

    protected $table = 'reporta';
     
    protected $fillable = [
        'user_id',
        'video_id',
        'detalle',
        'contenido_inapropiado',
        'spam',
        'contenido_enganoso',
        'violacion_derechos_autor',
        'incitacion_al_odio',
        'violencia_grafica',
        'otros',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
