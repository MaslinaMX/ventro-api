<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\Venta;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function resumenMes(Request $request)
    {
        $user = $request->user();
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        $ventasQuery = Venta::where('estado', 'completada')
            ->whereBetween('created_at', [$inicioMes, $finMes]);

        $gastosQuery = Gasto::whereBetween('fecha', [$inicioMes, $finMes]);

        // Si el usuario está limitado a una sucursal, filtramos ambos
        // totales para que solo reflejen su propia sucursal.
        if ($user->isScopedToSucursal()) {
            $ventasQuery->whereHas(
                'sesionCaja.caja',
                fn ($q) => $q->where('sucursal_id', $user->sucursal_id)
            );
            $gastosQuery->where('sucursal_id', $user->sucursal_id);
        }

        $vendido = round((float) $ventasQuery->sum('total'), 2);
        $gastado = round((float) $gastosQuery->sum('monto'), 2);

        return response()->json([
            'vendido' => $vendido,
            'gastado' => $gastado,
            'neto' => round($vendido - $gastado, 2),
        ]);
    }
}
