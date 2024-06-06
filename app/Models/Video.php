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
        'activo',
        'canal_id',
        'miniatura'
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
}