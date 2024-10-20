<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class PlanController extends Controller
{

    public function registrarPlan(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'nombre_plan' => 'required|string',
            'metodo_de_pago' => 'required|string',
        ]);
        $user = User::find($request->user_id);
        $user->premium = 1;
        $user->save();

        Plan::create([
            'nombre' => $request->nombre_plan,
            'metodo_de_pago' => $request->metodo_de_pago,
            'fecha_pago' => now(),
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
        $plan = Plan::where('user_id', $user_id)->first();
        if (!$plan) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }
        return response()->json(['success' => true, 'plan' => $plan]);
    }

    public function bajaPlan($user_id)
    {
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }
        $plan = Plan::where('user_id', $user_id)->first();
        if (!$plan) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }
        $plan->fecha_cancelacion = now();
        $plan->save();
        $user->premium = 0;
        $user->save();
        return response()->json(['success' => true, 'message' => 'El plan ha sido dado de baja y se ha registrado la fecha de cancelación.']);
    }
}
