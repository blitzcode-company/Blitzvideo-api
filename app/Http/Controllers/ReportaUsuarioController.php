<?php
namespace App\Http\Controllers;

use App\Models\ReportaUsuario;
use Illuminate\Http\Request;

class ReportaUsuarioController extends Controller
{
    public function CrearReporte(Request $request)
    {
        $validatedData = $request->validate([
            'id_reportante'          => 'required|exists:users,id',
            'id_reportado'           => 'required|exists:users,id',
            'detalle'                => 'nullable|string',
            'ciberacoso'             => 'nullable|boolean',
            'privacidad'             => 'nullable|boolean',
            'suplantacion_identidad' => 'nullable|boolean',
            'amenazas'               => 'nullable|boolean',
            'incitacion_odio'        => 'nullable|boolean',
            'otros'                  => 'nullable|boolean',
        ]);

        $reporte = ReportaUsuario::create($validatedData);

        return response()->json([
            'message' => 'Reporte creado exitosamente.',
            'reporte' => $reporte,
        ], 201);
    }
    public function listarReportes()
    {
        $reportes = ReportaUsuario::all();

        return response()->json($reportes);
    }
    public function listarReportesPorUsuario($userId)
    {
        $reportes = ReportaUsuario::where('id_reportado', $userId)->get();

        return response()->json($reportes);
    }
    public function listarReportesPorReportante($userId)
    {
        $reportes = ReportaUsuario::where('id_reportante', $userId)->get();

        return response()->json($reportes);
    }
}
