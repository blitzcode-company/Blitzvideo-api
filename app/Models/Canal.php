<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Canal extends Model
{
    use SoftDeletes;
    protected $table = 'canals';
    protected $fillable = [
        'nombre', 'descripcion', 'portada', 'user_id',
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
        return $this->belongsToMany(User::class, 'suscribe')->withTimestamps()->withTrashed();
    }
}
