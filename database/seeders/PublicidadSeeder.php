<?php

namespace Database\Seeders;

use App\Models\Canal;
use App\Models\Publicidad;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PublicidadSeeder extends Seeder
{
    public function run()
    {
        $empresas = [
            [
                'empresa' => 'McDonald\'s',
                'email' => 'mcdonalds@correo.com',
                'canal_nombre' => 'Canal McDonald\'s',
                'video' => [
                    'titulo' => 'Publicidad McDonald\'s',
                    'duracion' => 30,
                    'descripcion' => 'Publicidad principal de McDonald\'s',
                ],
                'prioridad' => 1,
            ],
            [
                'empresa' => 'Nike',
                'email' => 'nike@correo.com',
                'canal_nombre' => 'Canal Nike',
                'video' => [
                    'titulo' => 'Publicidad Nike',
                    'duracion' => 45,
                    'descripcion' => 'Publicidad principal de Nike',
                ],
                'prioridad' => 2,
            ],
            [
                'empresa' => 'Microsoft',
                'email' => 'microsoft@correo.com',
                'canal_nombre' => 'Canal Microsoft',
                'video' => [
                    'titulo' => 'Publicidad Microsoft',
                    'duracion' => 60,
                    'descripcion' => 'Publicidad principal de Microsoft',
                ],
                'prioridad' => 3,
            ],
        ];

        foreach ($empresas as $empresa) {
            $user = User::create([
                'name' => $empresa['empresa'],
                'email' => $empresa['email'],
                'password' => Hash::make('password'),
                'fecha_de_nacimiento' => '1990-01-01',
                'premium' => false,
            ]);
            $canal = Canal::create([
                'nombre' => $empresa['canal_nombre'],
                'descripcion' => 'Canal oficial de ' . $empresa['empresa'],
                'portada' => 'portada.png',
                'user_id' => $user->id,
            ]);
            $video = new Video([
                'titulo' => $empresa['video']['titulo'],
                'descripcion' => $empresa['video']['descripcion'],
                'link' => 'https://www.example.com/' . strtolower($empresa['empresa']),
                'miniatura' => strtolower($empresa['empresa']) . '.png',
                'duracion' => $empresa['video']['duracion'],
                'bloqueado' => false,
                'acceso' => 'publico',
            ]);
            $video->canal_id = $canal->id;
            $video->save();
            $publicidad = Publicidad::create([
                'empresa' => $empresa['empresa'],
                'prioridad' => $empresa['prioridad'],
            ]);
            $publicidad->video()->attach($video->id);
        }
    }
}
