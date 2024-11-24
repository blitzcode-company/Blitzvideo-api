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
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
