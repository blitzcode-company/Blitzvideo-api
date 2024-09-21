<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titulo',
        'descripcion',
        'link',
        'canal_id',
        'miniatura',
        'bloqueado', 
        'acceso',      
    ];

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }

    public function etiquetas()
    {
        return $this->belongsToMany(Etiqueta::class);
    }

    public function visitas()
    {
        return $this->hasMany(Visita::class);
    }

    public function puntuaciones()
    {
        return $this->hasMany(Puntua::class);
    }

    public function getPuntuacionPromedioAttribute()
    {
        $promedio = $this->puntuaciones()->avg('valora');
        return round($promedio);
    }

    public function getVisitasCountAttribute()
    {
        return $this->visitas()->count();
    }

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class, 'video_lista');
    }
}
