<?php
namespace App\Models;

use App\Models\Canal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'stream_programado',
        'max_viewers',
        'total_viewers',
        'activo',
    ];
    public function video()
    {
        return $this->belongsTo(Video::class);
    }
    public function canales()
    {
        return $this->belongsToMany(Canal::class, 'canal_stream');
    }
}
