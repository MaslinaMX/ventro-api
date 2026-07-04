<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Concerns\VerificaEmpleadoPorPin;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CorteCaja;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SesionCajaController extends Controller
{
    use VerificaEmpleadoPorPin;

    public function activa(Request $request, $cajaId)
    {
        $sesion = SesionCaja::where('caja_id', $cajaId)
            ->where('estado', 'abierta')
            ->with('usuario')
            ->first();

        return response()->json($sesion);
    }

    public function abrir(Request $request, $cajaId)
    {
        $empleado = $this->verificarEmpleadoPin($request);

        if (! $empleado->hasPermission('caja.abrir')) {
            return response()->json(['message' => 'Este empleado no tiene permiso para abrir caja.'], 403);
        }

        $caja = Caja::findOrFail($cajaId);

        $yaAbierta = SesionCaja::where('caja_id', $caja->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($yaAbierta) {
            return response()->json(['message' => 'Esta caja ya tiene una sesión abierta.'], 422);
        }

        $empleadoYaTieneSesionAbierta = SesionCaja::where('usuario_id', $empleado->id)
            ->where('estado', 'abierta')
            ->exists();

        if ($empleadoYaTieneSesionAbierta) {
            return response()->json(['message' => 'Este empleado ya tiene una sesión de caja abierta en otra caja.'], 422);
        }

        $montoInicial = $request->validate([
            'monto_inicial' => 'required|numeric|min:0',
        ])['monto_inicial'];

        $sesion = SesionCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => $empleado->id,
            'monto_inicial' => $montoInicial,
            'estado' => 'abierta',
            'abierta_en' => now(),
        ]);

        return response()->json($sesion->load('usuario'), 201);
    }

    public function cerrar(Request $request, $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);
        $sesion = SesionCaja::findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión ya está cerrada.'], 422);
        }

        $esElMismo = $sesion->usuario_id === $empleado->id;
        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esElMismo && ! $esAdmin) {
            return response()->json(['message' => 'Solo el cajero que abrió esta sesión, o un administrador, puede cerrarla.'], 403);
        }

        $montoContado = $request->validate([
            'monto_final_contado' => 'required|numeric|min:0',
        ])['monto_final_contado'];

        $montoEsperado = $sesion->monto_inicial; // TODO Fase 5: + ventas en efectivo

        $sesion->update([
            'monto_final_esperado' => $montoEsperado,
            'monto_final_contado' => $montoContado,
            'diferencia' => $montoContado - $montoEsperado,
            'estado' => 'cerrada',
            'cerrada_por_id' => $empleado->id,
            'cerrada_en' => now(),
        ]);

        return response()->json($sesion->load(['usuario', 'cerradaPor']));
    }

    public function corteX(Request $request, $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);
        $sesion = SesionCaja::with('usuario')->findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión no está abierta.'], 422);
        }

        $esElMismo = $sesion->usuario_id === $empleado->id;
        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esElMismo && ! $esAdmin) {
            return response()->json(['message' => 'Solo el cajero que abrió esta sesión, o un administrador, puede ver este corte.'], 403);
        }

        $reporte = $this->construirReporteCorte($sesion);

        $corte = CorteCaja::create([
            'sesion_caja_id' => $sesion->id,
            'tipo' => 'X',
            'snapshot' => $reporte,
            'generado_por_id' => $empleado->id,
            'generado_en' => now(),
        ]);

        $reporte['corte_id'] = $corte->id;
        $reporte['generado_por'] = $empleado->name;
        $reporte['generado_en'] = $corte->generado_en->format('d/m/Y H:i:s');

        return response()->json($reporte);
    }

    public function corteZ(Request $request, $id)
    {
        $empleado = $this->verificarEmpleadoPin($request);
        $sesion = SesionCaja::with('usuario')->findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión ya está cerrada.'], 422);
        }

        $esElMismo = $sesion->usuario_id === $empleado->id;
        $esAdmin = $empleado->role === User::ROLE_ADMIN_EMPRESA
            || $empleado->role === User::ROLE_ADMIN_SUCURSAL;

        if (! $esElMismo && ! $esAdmin) {
            return response()->json(['message' => 'Solo el cajero que abrió esta sesión, o un administrador, puede cerrarla.'], 403);
        }

        $montoContado = $request->validate([
            'monto_final_contado' => 'required|numeric|min:0',
        ])['monto_final_contado'];

        $reporte = $this->construirReporteCorte($sesion);

        $efectivoEsperado = $reporte['efectivo_esperado'];
        $diferencia = round($montoContado - $efectivoEsperado, 2);

        if ($diferencia > 0) {
            $status = 'sobrante';
        } elseif ($diferencia < 0) {
            $status = 'faltante';
        } else {
            $status = 'exacto';
        }

        $corte = DB::transaction(function () use ($sesion, $empleado, $reporte, $montoContado, $efectivoEsperado, $diferencia, $status) {
            $sesion->update([
                'monto_final_esperado' => $efectivoEsperado,
                'monto_final_contado' => $montoContado,
                'diferencia' => $diferencia,
                'estado' => 'cerrada',
                'cerrada_por_id' => $empleado->id,
                'cerrada_en' => now(),
            ]);

            return CorteCaja::create([
                'sesion_caja_id' => $sesion->id,
                'tipo' => 'Z',
                'snapshot' => $reporte,
                'efectivo_contado' => $montoContado,
                'diferencia' => $diferencia,
                'status' => $status,
                'generado_por_id' => $empleado->id,
                'generado_en' => now(),
            ]);
        });

        $reporte['corte_id'] = $corte->id;
        $reporte['generado_por'] = $empleado->name;
        $reporte['generado_en'] = $corte->generado_en->format('d/m/Y H:i:s');
        $reporte['efectivo_contado'] = $montoContado;
        $reporte['diferencia'] = $diferencia;
        $reporte['status'] = $status;

        return response()->json($reporte);
    }

    private function construirReporteCorte(SesionCaja $sesion): array
    {
        $ventasCompletadas = Venta::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'completada')
            ->with('pagos.metodoPago', 'usuario')
            ->get();

        $ventasCanceladas = Venta::where('sesion_caja_id', $sesion->id)
            ->where('estado', 'cancelada')
            ->with('devolucion.metodoDevolucion', 'pagos.metodoPago', 'usuario')
            ->get();

        // Una cancelación es "total" si se devolvió el 100% del monto de la
        // venta. Si se devolvió menos, fue parcial y una parte de la venta
        // original sigue contando como venta real.
        $esCancelacionTotal = function (Venta $venta): bool {
            if (! $venta->devolucion) {
                return true;
            }

            return (float) $venta->devolucion->monto_devuelto >= (float) $venta->total - 0.01;
        };

        $ventasParciales = $ventasCanceladas->reject($esCancelacionTotal);

        // Desglose por método de pago: se parte del bruto de TODAS las ventas
        // (completadas + canceladas), y luego se resta el prorrateo de las
        // devoluciones. Así una cancelación total termina en $0 y una parcial
        // deja únicamente su parte conservada.
        $totalesPorMetodo = [];
        foreach ($ventasCompletadas->merge($ventasCanceladas) as $venta) {
            foreach ($venta->pagos as $pago) {
                $nombreMetodo = $pago->metodoPago->nombre;
                $totalesPorMetodo[$nombreMetodo] = ($totalesPorMetodo[$nombreMetodo] ?? 0) + (float) $pago->monto;
            }
        }

        foreach ($ventasCanceladas as $venta) {
            if (! $venta->devolucion) {
                continue;
            }

            $montoDevuelto = (float) $venta->devolucion->monto_devuelto;
            $totalVenta = (float) $venta->total;

            if ($totalVenta <= 0) {
                continue;
            }

            foreach ($venta->pagos as $pago) {
                $nombreMetodo = $pago->metodoPago->nombre;
                $proporcion = (float) $pago->monto / $totalVenta;
                $aRestar = round($montoDevuelto * $proporcion, 2);
                $totalesPorMetodo[$nombreMetodo] = ($totalesPorMetodo[$nombreMetodo] ?? 0) - $aRestar;
            }
        }

        // efectivo_ventas se mantiene igual: es el bruto cobrado en efectivo de
        // ventas completadas, usado junto con efectivo_canceladas/efectivo_devuelto
        // para el cálculo físico de caja — no debe mezclarse con el neto por método.
        $efectivoVentas = 0;
        foreach ($ventasCompletadas as $venta) {
            foreach ($venta->pagos as $pago) {
                if (strtolower($pago->metodoPago->nombre) === 'efectivo') {
                    $efectivoVentas += (float) $pago->monto;
                }
            }
        }

        $efectivoCanceladas = 0;
        foreach ($ventasCanceladas as $venta) {
            foreach ($venta->pagos as $pago) {
                if (strtolower($pago->metodoPago->nombre) === 'efectivo') {
                    $efectivoCanceladas += (float) $pago->monto;
                }
            }
        }

        $efectivoDevuelto = 0;
        foreach ($ventasCanceladas as $venta) {
            if ($venta->devolucion && strtolower($venta->devolucion->metodoDevolucion->nombre) === 'efectivo') {
                $efectivoDevuelto += (float) $venta->devolucion->monto_devuelto;
            }
        }

        $montoInicial = (float) $sesion->monto_inicial;
        $efectivoEsperado = round($montoInicial + $efectivoVentas + $efectivoCanceladas - $efectivoDevuelto, 2);

        $totalVentasNeto = round(array_sum($totalesPorMetodo), 2);

        // Listado principal: completadas normales + parciales conservadas
        // (marcadas con nota), en orden de hora.
        $listado = $ventasCompletadas->map(fn ($v) => [
            'id' => $v->id,
            'numero_ticket' => $v->numero_ticket,
            'numero_ticket_completo' => tenant()->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
            'total' => (float) $v->total,
            'cajero' => $v->usuario->name ?? '—',
            'metodos_pago' => $v->pagos->map(fn ($p) => [
                'nombre' => $p->metodoPago->nombre,
                'referencia' => $p->referencia,
            ])->unique('nombre')->values(),
            'hora' => $v->created_at->format('H:i'),
            'cancelacion_parcial' => false,
        ])->merge($ventasParciales->map(fn ($v) => [
            'id' => $v->id,
            'numero_ticket' => $v->numero_ticket,
            'numero_ticket_completo' => tenant()->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
            'total' => round((float) $v->total - (float) $v->devolucion->monto_devuelto, 2),
            'cajero' => $v->usuario->name ?? '—',
            'metodos_pago' => $v->pagos->map(fn ($p) => [
                'nombre' => $p->metodoPago->nombre,
                'referencia' => $p->referencia,
            ])->unique('nombre')->values(),
            'hora' => $v->created_at->format('H:i'),
            'cancelacion_parcial' => true,
            'monto_original' => (float) $v->total,
            'monto_devuelto' => (float) $v->devolucion->monto_devuelto,
        ]))->sortBy('hora')->values();

        return [
            'sesion' => $sesion,
            'monto_inicial' => $montoInicial,
            'efectivo_ventas' => round($totalesPorMetodo['Efectivo'] ?? 0, 2),
            'efectivo_ventas_completadas' => round($efectivoVentas, 2),
            'efectivo_canceladas' => round($efectivoCanceladas, 2),
            'efectivo_devuelto' => round($efectivoDevuelto, 2),
            'efectivo_esperado' => $efectivoEsperado,
            'totales_por_metodo' => collect($totalesPorMetodo)->map(fn ($monto, $nombre) => [
                'metodo' => $nombre,
                'total' => round($monto, 2),
            ])->values(),
            'total_ventas' => $totalVentasNeto,
            'cantidad_ventas' => $ventasCompletadas->count() + $ventasParciales->count(),
            'ventas_listado' => $listado,
            'ventas_canceladas' => $ventasCanceladas->map(fn ($v) => [
                'id' => $v->id,
                'numero_ticket' => tenant()->codigo_ticket.str_pad((string) $v->numero_ticket, 4, '0', STR_PAD_LEFT),
                'total' => (float) $v->total,
                'cancelada_en' => $v->cancelada_en?->format('d/m/Y H:i'),
                'devolucion' => $v->devolucion ? [
                    'monto_devuelto' => (float) $v->devolucion->monto_devuelto,
                    'metodo' => $v->devolucion->metodoDevolucion->nombre,
                    'motivo' => $v->devolucion->motivo,
                ] : null,
            ])->values(),
        ];
    }

    public function previewCierre(Request $request, $id)
    {
        $sesion = SesionCaja::findOrFail($id);

        if (! $sesion->isAbierta()) {
            return response()->json(['message' => 'Esta sesión no está abierta.'], 422);
        }

        $reporte = $this->construirReporteCorte($sesion);

        return response()->json([
            'monto_inicial' => $reporte['monto_inicial'],
            'efectivo_ventas' => $reporte['efectivo_ventas'],
            'efectivo_esperado' => $reporte['efectivo_esperado'],
            'total_ventas' => $reporte['total_ventas'],
            'cantidad_ventas' => $reporte['cantidad_ventas'],
        ]);
    }
}
