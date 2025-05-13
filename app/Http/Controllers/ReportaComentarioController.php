<?php
namespace App\Http\Controllers;

use App\Models\ReportaComentario;
use Illuminate\Http\Request;

class ReportaComentarioController extends Controller
{
    public function CrearReporte(Request $request)
    {
        $validatedData = $request->validate([
            'user_id'            => 'required|exists:users,id',
            'comentario_id'      => 'required|exists:comentarios,id',
            'detalle'            => 'nullable|string',
            'lenguaje_ofensivo'  => 'nullable|boolean',
            'spam'               => 'nullable|boolean',
            'contenido_enganoso' => 'nullable|boolean',
            'incitacion_al_odio' => 'nullable|boolean',
            'acoso'              => 'nullable|boolean',
            'contenido_sexual'   => 'nullable|boolean',
            'otros'              => 'nullable|boolean',
        ]);

        $reporte = ReportaComentario::create($validatedData);

        return response()->json([
            'message' => 'Reporte de comentario creado exitosamente.',
            'reporte' => $reporte,
        ], 201);
    }

    public function listarReportes()
    {
        $reportes = ReportaComentario::all();

        return response()->json($reportes);
    }

    public function listarReportesPorComentario($comentarioId)
    {
        $reportes = ReportaComentario::where('comentario_id', $comentarioId)->get();

        return response()->json($reportes);
    }

    public function listarReportesPorUsuario($userId)
    {
        $reportes = ReportaComentario::where('user_id', $userId)->get();

        return response()->json($reportes);
    }
}
