<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notifica extends Model
{
    protected $table = 'notifica';

    protected $fillable = [
        'usuario_id',
        'notificacion_id',
        'leido',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function notificacion()
    {
        return $this->belongsTo(Notificacion::class);
    }
}
