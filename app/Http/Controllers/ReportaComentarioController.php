<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReportaComentario;
use App\Models\Comentario;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class ReportaComentarioController extends Controller
{
    public function CrearReporte(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'comentario_id' => 'required|exists:comentarios,id',
            'detalle' => 'nullable|string',
            'lenguaje_ofensivo' => 'nullable|boolean',
            'spam' => 'nullable|boolean',
            'contenido_enganoso' => 'nullable|boolean',
            'incitacion_al_odio' => 'nullable|boolean',
            'acoso' => 'nullable|boolean',
            'contenido_sexual' => 'nullable|boolean',
            'otros' => 'nullable|boolean',
        ]);

        $reporte = ReportaComentario::create($validatedData);

        return response()->json([
            'message' => 'Reporte de comentario creado exitosamente.',
            'reporte' => $reporte
        ], 201);
    }
}
