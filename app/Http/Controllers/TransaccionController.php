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
            'user_id'        => 'required|exists:users,id',
            'plan'           => 'required|string',
            'metodo_de_pago' => 'required|string',
        ]);

        $user = $this->findUser($request->user_id);
        $this->updateUserPremiumStatus($user, true);

        Transaccion::create([
            'plan'           => $request->plan,
            'metodo_de_pago' => $request->metodo_de_pago,
            'fecha_inicio'   => now(),
            'user_id'        => $user->id,
            'suscripcion_id' => $request->suscripcion_id ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Usuario actualizado a premium.']);
    }

    public function listarPlan($user_id)
    {
        $user = $this->findUser($user_id);
        if (! $user) {
            return $this->userNotFoundResponse();
        }

        $transaccion = Transaccion::where('user_id', $user_id)->first();
        if (! $transaccion) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }

        return response()->json(['success' => true, 'transaccion' => $transaccion]);
    }

    public function bajaPlan($user_id)
    {
        $user = $this->findUser($user_id);
        if (! $user) {
            return $this->userNotFoundResponse();
        }

        $transaccion = Transaccion::where('user_id', $user_id)->first();
        if (! $transaccion) {
            return response()->json(['success' => false, 'message' => 'El usuario no tiene un plan activo.']);
        }

        $transaccion->update(['fecha_cancelacion' => now()]);
        $this->updateUserPremiumStatus($user, false);

        return response()->json(['success' => true, 'message' => 'El plan ha sido dado de baja y se ha registrado la fecha de cancelación.']);
    }

    private function findUser($user_id)
    {
        return User::find($user_id);
    }

    private function updateUserPremiumStatus(User $user, bool $status)
    {
        $user->premium = $status ? 1 : 0;
        $user->save();
    }

    private function userNotFoundResponse()
    {
        return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }
}
