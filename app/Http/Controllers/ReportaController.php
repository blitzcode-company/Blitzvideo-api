<?php

namespace App\Http\Controllers;

use App\Models\Reporta;
use Illuminate\Http\Request;

class ReportaController extends Controller
{
    public function CrearReporte(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'video_id' => 'required|exists:videos,id',
            'detalle' => 'nullable|string',
            'contenido_inapropiado' => 'nullable|boolean',
            'spam' => 'nullable|boolean',
            'contenido_enganoso' => 'nullable|boolean',
            'violacion_derechos_autor' => 'nullable|boolean',
            'incitacion_al_odio' => 'nullable|boolean',
            'violencia_grafica' => 'nullable|boolean',
            'otros' => 'nullable|boolean',
        ]);

        $reporte = Reporta::create($validatedData);

        return response()->json([
            'message' => 'Reporte creado exitosamente.',
            'reporte' => $reporte,
        ], 201);
    }
}
