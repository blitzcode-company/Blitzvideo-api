<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    use HasFactory;

    protected $table = 'transaccion';
    public $timestamps = false;

    protected $fillable = [
        'plan',
        'metodo_de_pago',
        'fecha_inicio',
        'fecha_cancelacion',
        'suscripcion_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
