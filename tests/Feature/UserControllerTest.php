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
    public function puede_convertir_usuario_en_premium_exitosamente()
    {
        $user = User::factory()->create([
            'premium' => rand(0, 1), 
        ]);

        $response = $this->post(env('BLITZVIDEO_BASE_URL') . 'usuario/premium', [
            'user_id' => 11
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Usuario actualizado a premium.',
                 ]);

        $this->assertDatabaseHas('users', [
            'id' => 11,
            'premium' => 1, 
        ]);
    }

    public function Falla_si_no_se_envia_el_user_id()
    {
        $response = $this->post(env('BLITZVIDEO_BASE_URL') . 'usuario/premium', []);
    
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);
    }

    public function falla_si_el_user_id_no_existe()
    {
        $response = $this->post(env('BLITZVIDEO_BASE_URL') . 'usuario/premium', [
            'user_id' => 9999, 
        ]);
    
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['user_id']);
    }

}
