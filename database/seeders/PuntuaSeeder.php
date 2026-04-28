<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Puntua;
use App\Models\Video;
use App\Models\User;
use Carbon\Carbon;

class PuntuaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $videos = Video::all();
        $users = User::all();
        
        if ($videos->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No hay videos o usuarios para crear puntuaciones');
            return;
        }

        foreach ($videos as $video) {
            $numPuntuaciones = rand(5, 30);
            
            $usuariosParaPuntuar = $users->random(min($numPuntuaciones, $users->count()));
            
            foreach ($usuariosParaPuntuar as $user) {
                $probabilidad = rand(1, 100);
                if ($probabilidad <= 40) {
                    $puntuacion = 5;
                } elseif ($probabilidad <= 70) {
                    $puntuacion = 4;
                } elseif ($probabilidad <= 85) {
                    $puntuacion = 3;
                } elseif ($probabilidad <= 95) {
                    $puntuacion = 2;
                } else {
                    $puntuacion = 1;
                }
                
                $fecha = Carbon::now()->subDays(rand(0, 90));
                
                Puntua::create([
                    'video_id' => $video->id,
                    'user_id' => $user->id,
                    'valora' => $puntuacion,
                    'created_at' => $fecha,
                    'updated_at' => $fecha
                ]);
            }
        }
        
        $this->command->info('Puntuaciones creadas: ' . Puntua::count());
    }
}
