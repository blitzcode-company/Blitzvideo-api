<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Stream;

class Canal extends Model
{
    use SoftDeletes;
    protected $table = 'canals';
    protected $fillable = [
        'nombre', 'descripcion', 'portada', 'user_id','stream_key',
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

    public function streamActual()
    {
        return $this->hasOne(Stream::class, 'canal_id')
            ->where('activo', true)
            ->latest('id');
    }
    public function streams()
    {
        return $this->hasOne(Stream::class);
    }
}
