<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
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
                'fecha_de_nacimiento',
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

    public function testDarDeBajaUsuario()
    {
        $user = new User();
        $user->name = 'Matias';
        $user->email = 'Matias@gmail.com';
        $user->fecha_de_nacimiento = '2003-04-11';
        $user->password = bcrypt('password');
        $user->save();

        $response = $this->delete(env('BLITZVIDEO_BASE_URL') . 'usuario/' . $user->id);
        $response->assertStatus(200)
            ->assertJson(['message' => 'El usuario se ha dado de baja correctamente']);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
