<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Models\Visita;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use WithoutMiddleware;
    
    public function testListarUsuarios()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'usuario');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'premium',
                'foto',
                'created_at',
                'updated_at',
                'canales' => [
                    '*' => [
                        'id',
                        'user_id',
                        'nombre',
                        'descripcion',
                        'portada',
                        'deleted_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
    }

    public function testVisitarVideo()
    {
        $user = User::inRandomOrder()->first();
        $video = Video::inRandomOrder()->first();

        Visita::create([
            'user_id' => $user->id,
            'video_id' => $video->id,
        ]);

        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'usuario/' . $user->id . '/visita/' . $video->id);
        $response->assertStatus(200);

        $response->assertJson([
            'message' => 'Visita registrada con éxito',
        ]);
    }

    public function testDarDeBajaUsuario()
    {
        $user = new User();
        $user->name = 'Matias';
        $user->email = 'Matias@gmail.com';
        $user->password = bcrypt('password');
        $user->save();

        $response = $this->delete(env('BLITZVIDEO_BASE_URL') . 'usuario/' . $user->id);
        $response->assertStatus(200)
            ->assertJson(['message' => 'El usuario se ha dado de baja correctamente']);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
