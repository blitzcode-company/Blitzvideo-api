<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reporta;
use App\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\Http;



class ReportaController extends Controller
{
    public function CrearReporte(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'video_id' => 'required|exists:videos,id',
            'detalle' => 'nullable|string',
            'contenido_inapropiado' => 'boolean',
            'spam' => 'boolean',
            'contenido_enganoso' => 'boolean',
            'violacion_derechos_autor' => 'boolean',
            'incitacion_al_odio' => 'boolean',
            'violencia_grafica' => 'boolean',
            'otros' => 'boolean',
        ]);

        $reporte = Reporta::create($validatedData);

        return response()->json([
            'message' => 'Reporte creado exitosamente.',
            'reporte' => $reporte
        ], 201);
    }

    public function ListarReportes()
    {
        $reportes = Reporta::with(['usuario', 'video'])->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ListarReportesDeVideo($videoId)
    {
        $reportes = Reporta::where('video_id', $videoId)->with('usuario')->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ListarReportesDeUsuario($userId)
    {
        $reportes = Reporta::where('user_id', $userId)->with('video')->get();

        return response()->json([
            'reportes' => $reportes
        ], 200);
    }

    public function ModificarReporte(Request $request, $reporteId)
    {
        $validatedData = $request->validate([
            'detalle' => 'nullable|string',
            'contenido_inapropiado' => 'boolean',
            'spam' => 'boolean',
            'contenido_enganoso' => 'boolean',
            'violacion_derechos_autor' => 'boolean',
            'incitacion_al_odio' => 'boolean',
            'violencia_grafica' => 'boolean',
            'otros' => 'boolean',
        ]);

        $reporte = Reporta::findOrFail($reporteId);
        $reporte->update($validatedData);

        return response()->json([
            'message' => 'Reporte modificado exitosamente.',
            'reporte' => $reporte
        ], 200);
    }

    public function BorrarReporte($reporteId)
    {
        $reporte = Reporta::findOrFail($reporteId);
        $reporte->delete();

        return response()->json([
            'message' => 'Reporte borrado exitosamente.'
        ], 200);
    }

    public function BorrarReportesDeVideo($videoId)
    {
        $reportes = Reporta::where('video_id', $videoId)->get();

        foreach ($reportes as $reporte) {
            $reporte->delete();
        }

        return response()->json([
            'message' => 'Todos los reportes del video han sido borrados exitosamente.'
        ], 200);
    }


}
