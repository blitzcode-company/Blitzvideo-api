<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function testListarUsuarios()
    {
        $response = $this->get(env('BLITZVIDEO_BASE_URL') . 'usuario');
        $response->assertStatus(200);
        $responseData = $response->json();

        $response->assertJsonStructure([
            '*' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'fecha_de_nacimiento',
                'premium',
                'bloqueado',
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


    public function testMostrarUsuarioPorId()
    {
        $user = User::latest()->first();
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "usuario/{$user->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'email',
            'email_verified_at',
            'fecha_de_nacimiento',
            'premium',
            'bloqueado',
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
        ]);
        $response->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function testEditarUsuario()
    {
        $user = User::latest()->first();
        Storage::fake('s3');
        $file = UploadedFile::fake()->image('foto.jpg');
        $data = [
            'name' => 'New Name',
            'email' => 'newemail@example.com',
            'fecha_de_nacimiento' => '1995-05-05',
            'foto' => $file,
        ];
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "usuario/{$user->id}", $data);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Usuario actualizado correctamente',
            ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'newemail@example.com',
            'fecha_de_nacimiento' => '1995-05-05',
        ]);
        Storage::disk('s3')->assertExists('perfil/' . $user->id . '/' . $file->hashName());
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
