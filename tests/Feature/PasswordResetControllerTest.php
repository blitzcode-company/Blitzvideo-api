<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use App\Models\User;

class PasswordResetControllerTest extends TestCase
{

    use WithoutMiddleware;

    /** @test */
    public function test_enviar_restablecer_enlace_correo()
    {
        $user = User::find(2);
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . 'password/email', [
            'email' => $user->email,
        ]);
        $response->assertStatus(200)
            ->assertJson(['message' => __('passwords.sent')]);
        $token = Password::getRepository()->exists($user, Password::createToken($user));
        $this->assertTrue($token, 'El token de restablecimiento debería estar generado.');
    }

    /** @test */
    public function test_reset_password()
    {
        $user = User::find(2);
        $token = Password::createToken($user);

        $response = $this->postJson(route('password.reset'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => __('passwords.reset')]);
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password), 'La contraseña no se actualizó correctamente.');
    }
}
