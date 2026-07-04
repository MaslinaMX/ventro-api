<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\CorteCaja;
use Barryvdh\DomPDF\Facade\Pdf;

class CorteCajaController extends Controller
{
    public function pdf(int $corteId)
    {
        $corte = CorteCaja::with(['sesionCaja.caja.sucursal', 'sesionCaja.usuario', 'generadoPor'])
            ->findOrFail($corteId);

        $tenant = tenant();
        $snapshot = $corte->snapshot;

        $datosBase = [
            'tipo' => $corte->tipo,
            'nombreNegocio' => $tenant->razon_social ?: $tenant->name,
            'cajaNombre' => $corte->sesionCaja->caja->nombre,
            'sucursalNombre' => $corte->sesionCaja->caja->sucursal->nombre,
            'abiertaPor' => $corte->sesionCaja->usuario->name,
            'generadoPor' => $corte->generadoPor->name,
            'generadoEn' => $corte->generado_en->format('d/m/Y H:i:s'),
            'montoInicial' => $snapshot['monto_inicial'],
            'efectivoVentas' => $snapshot['efectivo_ventas'],
            'efectivoVentasCompletadas' => $snapshot['efectivo_ventas_completadas'] ?? $snapshot['efectivo_ventas'],
            'efectivoEsperado' => $snapshot['efectivo_esperado'],
            'totalesPorMetodo' => $snapshot['totales_por_metodo'],
            'totalVentas' => $snapshot['total_ventas'],
            'cantidadVentas' => $snapshot['cantidad_ventas'],
            'ventasListado' => $snapshot['ventas_listado'] ?? [],
            'ventasCanceladas' => $snapshot['ventas_canceladas'],
            'efectivoCanceladas' => $snapshot['efectivo_canceladas'] ?? 0,
            'efectivoDevuelto' => $snapshot['efectivo_devuelto'] ?? 0,
        ];

        if ($corte->tipo === 'Z') {
            $datosBase['efectivoContado'] = (float) $corte->efectivo_contado;
            $datosBase['diferencia'] = (float) $corte->diferencia;
            $datosBase['status'] = $corte->status;

            $pdf = Pdf::loadView('cortes.corte-z', $datosBase)
                ->setPaper('letter', 'portrait');
        } else {
            $pdf = Pdf::loadView('cortes.corte', $datosBase)
                ->setPaper([0, 0, 250, 500], 'portrait');
        }

        return $pdf->stream("corte-{$corte->tipo}-{$corte->id}.pdf");
    }
}
