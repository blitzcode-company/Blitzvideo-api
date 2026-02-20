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
        'duracion',
        'bloqueado',
        'acceso',
        'estado',
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

    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'video_id');
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

    public function publicidad()
    {
        return $this->belongsToMany(Publicidad::class, 'video_publicidad')
            ->withPivot('vistos')
            ->withTimestamps();
    }

    public function stream()
    {
        return $this->hasOne(Stream::class, 'video_id', 'id');
    }

    public function getVisitasTotalesAttribute()
    {
        return $this->visitas()->count();
    }

    public function getVisitasValidasAttribute()
    {
        return $this->visitas()->where('view_valida', true)->count();
    }

    public function getTiempoReproduccionSegundosAttribute()
    {
        return (int) $this->visitas()->sum('segundos_vistos');
    }

    public function getTiempoReproduccionMinutosAttribute()
    {
        return round($this->tiempo_reproduccion_segundos / 60, 1);
    }

    public function getDuracionPromedioVisualizacionAttribute()
    {
        $count = $this->visitas()->where('segundos_vistos', '>', 0)->count();
        return $count ? round($this->visitas()->sum('segundos_vistos') / $count) : 0;
    }

    public function getPorcentajePromedioVistoAttribute()
    {
        if ($this->duracion <= 0) return 0;
        $promedio = $this->visitas()->avg('segundos_vistos') ?? 0;
        return round(($promedio / $this->duracion) * 100, 1);
    }

    public function getTasaCompletadoAttribute()
    {
        $total = $this->visitas()->where('segundos_vistos', '>', 0)->count();
        if ($total == 0) return 0;
        $completados = $this->visitas()->where('completado', true)->count();
        return round(($completados / $total) * 100, 1);
    }

    public function getBucketsRetencionAttribute()
    {
        if ($this->duracion <= 0) return [];

        $buckets = collect(range(0, 100, 10))->mapWithKeys(fn($p) => [$p => 0]);

        $this->visitas()
            ->where('segundos_vistos', '>', 0)
            ->selectRaw("
                LEAST(100, FLOOR((segundos_vistos / ?) * 100 / 10) * 10) as bucket,
                COUNT(*) as cantidad
            ", [$this->duracion])
            ->groupBy('bucket')
            ->get()
            ->each(fn($row) => $buckets[$row->bucket] = (int)$row->cantidad);

        return $buckets->toArray();
    }

    public function aEstadisticasArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'duracion' => (int)$this->duracion,
            'visitas_totales' => $this->visitas_totales,
            'visitas_validas' => $this->visitas_validas,
            'tiempo_reproduccion_minutos' => $this->tiempo_reproduccion_minutos,
            'duracion_promedio_visualizacion' => $this->duracion_promedio_visualizacion,
            'porcentaje_promedio_visto' => $this->porcentaje_promedio_visto,
            'tasa_completado' => $this->tasa_completado,
            'retencion_buckets' => $this->buckets_retencion,
        ];
    }


}
