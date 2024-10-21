<?php

namespace Database\Seeders;

use App\Models\Transaccion;
use Illuminate\Database\Seeder;

class TransaccionSeeder extends Seeder
{
    public function run()
    {
        $transacciones = [
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Stripe',
                'fecha_inicio' => '2024-01-15',
                'fecha_cancelacion' => null,
                'suscripcion_id' => null,
                'user_id' => 2,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Paypal',
                'fecha_inicio' => '2024-02-10',
                'fecha_cancelacion' => '2024-03-01',
                'suscripcion_id' => 100102,
                'user_id' => 3,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Stripe',
                'fecha_inicio' => '2024-03-22',
                'fecha_cancelacion' => null,
                'suscripcion_id' => null,
                'user_id' => 4,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Paypal',
                'fecha_inicio' => '2024-01-05',
                'fecha_cancelacion' => '2024-01-25',
                'suscripcion_id' => 100104,
                'user_id' => 5,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Stripe',
                'fecha_inicio' => '2024-05-10',
                'fecha_cancelacion' => null,
                'suscripcion_id' => null,
                'user_id' => 6,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Paypal',
                'fecha_inicio' => '2024-04-12',
                'fecha_cancelacion' => '2024-04-28',
                'suscripcion_id' => 100106,
                'user_id' => 7,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Stripe',
                'fecha_inicio' => '2024-06-18',
                'fecha_cancelacion' => null,
                'suscripcion_id' => null,
                'user_id' => 8,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Paypal',
                'fecha_inicio' => '2024-07-01',
                'fecha_cancelacion' => '2024-07-10',
                'suscripcion_id' => 100108,
                'user_id' => 9,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Stripe',
                'fecha_inicio' => '2024-08-09',
                'fecha_cancelacion' => null,
                'suscripcion_id' => null,
                'user_id' => 10,
            ],
            [
                'plan' => 'Premium',
                'metodo_de_pago' => 'Paypal',
                'fecha_inicio' => '2024-09-11',
                'fecha_cancelacion' => null,
                'suscripcion_id' => 100110,
                'user_id' => 11,
            ],
        ];
        foreach ($transacciones as $data) {
            Transaccion::create($data);
        }
    }
}
