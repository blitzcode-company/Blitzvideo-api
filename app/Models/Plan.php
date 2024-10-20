<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'plan';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'metodo_de_pago',
        'fecha_pago',
        'fecha_cancelacion',
        'suscripcion_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
