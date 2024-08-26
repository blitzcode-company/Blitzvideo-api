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
            'lenguaje_ofensivo' => 'boolean',
            'spam' => 'boolean',
            'contenido_enganoso' => 'boolean',
            'incitacion_al_odio' => 'boolean',
            'acoso' => 'boolean',
            'contenido_sexual' => 'boolean',
            'otros' => 'boolean',
        ]);

        $reporte = ReportaComentario::create($validatedData);

        return response()->json([
            'message' => 'Reporte de comentario creado exitosamente.',
            'reporte' => $reporte
        ], 201);
    }

    public function ListarReportes()
    {
        $reportes = ReportaComentario::with(['user', 'comentario'])->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ListarReportesDeComentario($comentarioId)
    {
        $reportes = ReportaComentario::where('comentario_id', $comentarioId)->with('user')->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ListarReportesDeUsuario($userId)
    {
        $reportes = ReportaComentario::where('user_id', $userId)->with('comentario')->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ModificarReporte(Request $request, $reporteId)
    {
        $validatedData = $request->validate([
            'detalle' => 'nullable|string',
            'lenguaje_ofensivo' => 'boolean',
            'spam' => 'boolean',
            'contenido_enganoso' => 'boolean',
            'incitacion_al_odio' => 'boolean',
            'acoso' => 'boolean',
            'contenido_sexual' => 'boolean',
            'otros' => 'boolean',
        ]);

        $reporte = ReportaComentario::findOrFail($reporteId);
        $reporte->update($validatedData);

        return response()->json([
            'message' => 'Reporte de comentario modificado exitosamente.',
            'reporte' => $reporte
        ], 200);
    }

    public function BorrarReporte($reporteId)
    {
        $reporte = ReportaComentario::findOrFail($reporteId);
        $reporte->delete();

        return response()->json([
            'message' => 'Reporte de comentario borrado exitosamente.'
        ], 200);
    }

    public function BorrarReportesDeComentario($comentarioId)
    {
        $reportes = ReportaComentario::where('comentario_id', $comentarioId)->get();

        foreach ($reportes as $reporte) {
            $reporte->delete();
        }

        return response()->json([
            'message' => 'Todos los reportes del comentario han sido borrados exitosamente.'
        ], 200);
    }

   
}
