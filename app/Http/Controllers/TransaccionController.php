<?php

namespace App\Http\Controllers;

use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{

    public function registrarPlan(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan' => 'required|string',
            'metodo_de_pago' => 'required|string',
        ]);
        $user = User::find($request->user_id);
        $user->premium = 1;
        $user->save();

        Transaccion::create([
            'plan' => $request->plan,
            'metodo_de_pago' => $request->metodo_de_pago,
            'fecha_inicio' => now(),
            'user_id' => $user->id,
            'suscripcion_id' => $request->suscripcion_id,
        ]);
        return response()->json(['success' => true, 'message' => 'Usuario actualizado a premium.']);
    }

    public function listarPlan($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }
        $transaccion = Transaccion::where('user_id', $user_id)->first();
        if (!$transaccion) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }
        return response()->json(['success' => true, 'transaccion' => $transaccion]);
    }

    public function bajaPlan($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }
        $transaccion = Transaccion::where('user_id', $user_id)->first();
        if (!$transaccion) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }
        $transaccion->fecha_cancelacion = now();
        $transaccion->save();
        $user->premium = 0;
        $user->save();
        return response()->json(['success' => true, 'message' => 'El plan ha sido dado de baja y se ha registrado la fecha de cancelación.']);
    }
}
