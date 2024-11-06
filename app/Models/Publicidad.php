<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publicidad extends Model
{
    use SoftDeletes;

    protected $table = 'publicidad';
    protected $fillable = ['empresa', 'prioridad'];
    protected $dates = ['deleted_at'];

    public function video()
    {
        return $this->belongsToMany(Video::class, 'video_publicidad')
            ->withPivot('vistos')
            ->withTimestamps();
    }
}