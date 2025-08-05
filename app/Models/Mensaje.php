<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mensaje extends Model
{
    use SoftDeletes, HasFactory;
    
    protected $fillable = [
        'user_id',
        'stream_id',
        'mensaje',
        'bloqueado',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'bloqueado' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }



}
