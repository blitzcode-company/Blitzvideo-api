<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Video;


class Notificacion extends Model
{
    protected $table = 'notificacion';
    
    protected $fillable = [
        'mensaje',
        'referencia_id',
        'referencia_tipo',
    ];

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'notifica', 'notificacion_id', 'usuario_id')
                    ->withPivot('leido')
                    ->withTimestamps();
    }

}
