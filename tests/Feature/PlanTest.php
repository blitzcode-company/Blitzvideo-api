<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use WithoutMiddleware;

    public function test_registrar_plan()
    {
        $user_id = 2;
        $response = $this->postJson(env('BLITZVIDEO_BASE_URL') . "plan", [
            'user_id' => $user_id,
            'nombre_plan' => 'Plan Premium',
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
        $this->assertDatabaseHas('plan', [
            'nombre' => 'Plan Premium',
            'metodo_de_pago' => 'Paypal',
            'user_id' => $user_id,
        ]);
    }

    public function test_listar_plan()
    {
        $plan = Plan::first();
        if (!$plan) {
            $this->fail('No se encontró ningún plan en la base de datos.');
        }
        $user_id = $plan->user_id;
        $response = $this->getJson(env('BLITZVIDEO_BASE_URL') . "plan/usuario/{$user_id}");
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'plan' => [
                    'user_id' => $user_id,
                    'nombre' => $plan->nombre,
                    'metodo_de_pago' => $plan->metodo_de_pago,
                ],
            ]);
    }

    public function test_baja_plan()
    {
        $plan = Plan::first();
        $user_id = 2;
        $response = $this->deleteJson(env('BLITZVIDEO_BASE_URL') . "plan/usuario/{$user_id}");
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'El plan ha sido dado de baja y se ha registrado la fecha de cancelación.',
            ]);
        $this->assertDatabaseHas('users', [
            'id' => $user_id,
            'premium' => 0,
        ]);
        $this->assertDatabaseHas('plan', [
            'id' => $plan->id,
            'fecha_cancelacion' => now()->toDateString(),
        ]);
    }
}
