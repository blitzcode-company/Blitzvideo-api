<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descripcion',
        'stream_key',
        'activo',
        'canal_id',
    ];

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }
}
