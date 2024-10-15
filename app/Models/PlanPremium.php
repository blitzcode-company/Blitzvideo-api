<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPremium extends Model
{
    use HasFactory;

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
