<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'premium',
        'foto',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function canales()
    {
        return $this->hasMany(Canal::class);
    }

    public function visitas()
    {
        return $this->hasMany(Visita::class);
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function reportaComentarios()
    {
        return $this->hasMany(ReportaComentario::class);
    }

    public function canalesSuscritos()
    {
        return $this->belongsToMany(Canal::class, 'suscribe')->withTimestamps()->withTrashed();
    }

}
