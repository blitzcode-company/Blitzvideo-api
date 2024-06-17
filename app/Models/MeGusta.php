<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeGusta extends Model
{
    use HasFactory;

    protected $table = 'me_gusta';

    protected $fillable = [
        'usuario_id',
        'comentario_id',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function comentario()
    {
        return $this->belongsTo(Comentario::class, 'comentario_id');
    }
}
