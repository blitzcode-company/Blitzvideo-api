<?php
namespace App\Models;

use App\Models\Stream;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Canal extends Model
{
    use SoftDeletes;
    protected $table    = 'canals';
    protected $fillable = [
        'nombre', 'descripcion', 'portada', 'user_id', 'stream_key',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function suscriptores()
    {
        return $this->belongsToMany(User::class, 'suscribe')
            ->withPivot('notificaciones')
            ->withTimestamps()
            ->withTrashed();
    }

    public function streams()
    {
        return $this->belongsToMany(Stream::class, 'canal_stream');
    }

    public function streamActual()
    {
        return $this->belongsToMany(Stream::class, 'canal_stream')
            ->where('activo', true)
            ->latest('id');
    }

    public function getTotalVideosAttribute()
    {
        return $this->videos()->count();
    }

    public function getTotalVisitasAttribute()
    {
        return $this->videos()->withCount('visitas')->get()->sum('visitas_count');
    }

    public function getTotalVisitasValidasAttribute()
    {
        return Visita::whereIn('video_id', $this->videos->pluck('id'))
            ->where('view_valida', true)
            ->count();
    }

    public function getTotalHorasReproduccionAttribute()
    {
        $segundos = Visita::whereIn('video_id', $this->videos->pluck('id'))
            ->sum('segundos_vistos');
        return round($segundos / 3600, 2);
    }

    public function getVideosMasVistosAttribute($limite = 5)
    {
        return $this->videos()
            ->withCount('visitas')
            ->orderBy('visitas_count', 'desc')
            ->take($limite)
            ->get(['id', 'titulo', 'miniatura', 'duracion']);
    }
}
