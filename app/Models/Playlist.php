<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Playlist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'acceso',
        'user_id',
    ];

    public function playlist()
    {
        return $this;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videos()
    {
        return $this->belongsToMany(Video::class, 'video_lista')
                    ->withPivot('orden')
                    ->withTimestamps();
    }

    public function usuariosQueLaGuardaron()
    {
        return $this->belongsToMany(
            User::class,
            'playlist_guardadas',
            'playlist_id',
            'user_id'
        )->withPivot('orden')->withTimestamps();
    }

    public function guardadaPor()
    {
        return $this->usuariosQueLaGuardaron();
    }
}
