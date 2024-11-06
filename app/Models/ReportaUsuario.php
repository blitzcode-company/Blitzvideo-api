<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportaUsuario extends Model
{    
    use HasFactory, SoftDeletes; 

    protected $table = 'reporta_usuario';

    protected $fillable = [
        'id_reportado',
        'id_reportante',
        'ciberacoso',
        'privacidad',
        'suplantacion_identidad',
        'amenazas',
        'incitacion_odio',
        'otros',
        'detalle'
    ];

    public function reportante()
    {
        return $this->belongsTo(User::class, 'id_reportante');
    }

    public function reportado()
    {
        return $this->belongsTo(User::class, 'id_reportado');
    }
}
