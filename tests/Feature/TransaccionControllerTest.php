<?php

namespace Tests\Feature;

use App\Models\Transaccion;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class TransaccionControllerTest extends TestCase
{
    use WithoutMiddleware;

    public function test_registrar_plan()
    {
        $user_id = 2;
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "transaccion/plan", [
            'user_id' => $user_id,
            'plan' => 'Plan Premium',
            'metodo_de_pago' => 'Paypal',
            'suscripcion_id' => '123',
        ]);
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Usuario actualizado a premium.',
            ]);
        $this->assertDatabaseHas('users', [
            'id' => $user_id,
            'premium' => 1,
        ]);
        $this->assertDatabaseHas('transaccion', [
            'plan' => 'Plan Premium',
            'metodo_de_pago' => 'Paypal',
            'user_id' => $user_id,
        ]);
    }

    public function test_listar_plan()
    {
        $transaccion = Transaccion::first();
        if (!$transaccion) {
            $this->fail('No se encontró ningún plan en la base de datos.');
        }
        $user_id = $transaccion->user_id;
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "transaccion/plan/usuario/{$user_id}");
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'transaccion' => [
                    'user_id' => $user_id,
                    'plan' => $transaccion->plan,
                    'metodo_de_pago' => $transaccion->metodo_de_pago,
                ],
            ]);
    }

    public function test_baja_plan()
    {
        $transaccion = Transaccion::first();
        $user_id = 2;
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "transaccion/plan/usuario/{$user_id}");
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'El plan ha sido dado de baja y se ha registrado la fecha de cancelación.',
            ]);
        $this->assertDatabaseHas('users', [
            'id' => $user_id,
            'premium' => 0,
        ]);
        $this->assertDatabaseHas('transaccion', [
            'id' => $transaccion->id,
            'fecha_cancelacion' => now()->toDateString(),
        ]);
    }
}
