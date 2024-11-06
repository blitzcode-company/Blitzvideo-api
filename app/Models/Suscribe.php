<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Suscribe extends Model
{
    use SoftDeletes;

    protected $table = 'suscribe';

    protected $fillable = [
        'user_id', 'canal_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canal()
    {
        return $this->belongsTo(Canal::class);
    }
}
