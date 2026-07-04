<?php

namespace App\Http\Controllers;

use App\Mail\TicketVentaMail;
use App\Models\ConfiguracionTicket;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Picqer\Barcode\BarcodeGeneratorPNG;

class TicketController extends Controller
{
    /**
     * Arma el PDF del ticket de una venta. Compartido por descarga directa
     * y envío por email, para no duplicar la lógica de armado de datos.
     */
    private function construirPdf(Venta $venta, bool $esReimpresion = false): \Barryvdh\DomPDF\PDF
    {
        $venta->load(['items', 'pagos.metodoPago', 'usuario', 'sesionCaja.caja.sucursal']);

        $tenant = tenant();
        $config = ConfiguracionTicket::obtener();
        $sucursal = $venta->sesionCaja->caja->sucursal;

        $numeroTicketCompleto = $this->numeroTicketCompleto($venta);

        $logoBase64 = null;
        if ($config->mostrar_logo && $tenant->logo) {
            try {
                $response = Http::timeout(5)->get($tenant->logo);
                if ($response->successful()) {
                    $mime = $response->header('Content-Type') ?? 'image/png';
                    $logoBase64 = "data:{$mime};base64,".base64_encode($response->body());
                }
            } catch (\Throwable $e) {
                $logoBase64 = null;
            }
        }

        $items = $venta->items->map(fn ($item) => [
            'cantidad' => $item->cantidad,
            'nombre_snapshot' => $item->nombre_snapshot,
            'precio_unitario' => $item->precio_unitario,
            'subtotal' => $item->subtotal,
        ])->toArray();

        $pagos = $venta->pagos->map(fn ($pago) => [
            'metodo' => $pago->metodoPago->nombre,
            'monto' => (float) $pago->monto,
            'referencia' => $pago->referencia,
            'recibido' => $pago->recibido ? (float) $pago->recibido : null,
            'cambio' => $pago->recibido ? round($pago->recibido - $pago->monto, 2) : 0,
        ])->toArray();

        $ivaTotal = (float) $venta->iva_total;
        $iepsTotal = (float) $venta->ieps_total;
        $descuento = (float) $venta->descuento;
        $baseGravable = (float) $venta->base_gravable;

        // El IVA/IEPS por línea (items) es el desglose fiscal ORIGINAL de cada
        // producto, sin escalar por el descuento de venta. Se usa aquí solo
        // para reconstruir el total bruto (precio con impuestos ANTES del
        // descuento), que el ticket muestra como "TOTAL" inicial cuando sí
        // hubo descuento, para que el cliente vea el precio de lista real.
        $ivaOriginal = round((float) $venta->items->sum('iva_monto'), 2);
        $iepsOriginal = round((float) $venta->items->sum('ieps_monto'), 2);
        $totalBruto = round((float) $venta->subtotal + $ivaOriginal + $iepsOriginal, 2);

        $barcodeBase64 = base64_encode(
            (new BarcodeGeneratorPNG)->getBarcode($numeroTicketCompleto, BarcodeGeneratorPNG::TYPE_CODE_128)
        );

        return Pdf::loadView('tickets.venta', [
            'nombreNegocio' => $tenant->razon_social ?: $tenant->name,
            'direccion' => $sucursal->direccion,
            'ciudadEstadoCp' => trim(implode(', ', array_filter([
                $sucursal->ciudad,
                $sucursal->estado,
                $sucursal->codigo_postal,
            ]))),
            'rfc' => $sucursal->rfc,
            'telefono' => $sucursal->telefono,
            'mostrarLogo' => $config->mostrar_logo,
            'logoBase64' => $logoBase64,
            'items' => $items,
            'subtotal' => (float) $venta->subtotal,
            'totalBruto' => $totalBruto,
            'descuento' => $descuento,
            'baseGravable' => $baseGravable,
            'ivaTotal' => $ivaTotal,
            'iepsTotal' => $iepsTotal,
            'total' => (float) $venta->total,
            'pagos' => $pagos,
            'cajeroNombre' => $venta->usuario->name,
            'cajaNombre' => $venta->sesionCaja->caja->nombre,
            'barcodeBase64' => $barcodeBase64,
            'numeroTicketCompleto' => $numeroTicketCompleto,
            'fechaFormateada' => $venta->created_at->format('d/m/Y H:i:s'),
            'mensajePersonalizado' => $config->mensaje_personalizado,
            'esReimpresion' => $esReimpresion,
        ])->setPaper([0, 0, 250, 500], 'portrait');
    }

    private function numeroTicketCompleto(Venta $venta): string
    {
        $tenant = tenant();

        return $tenant->codigo_ticket.str_pad((string) $venta->numero_ticket, 4, '0', STR_PAD_LEFT);
    }

    /**
     * GET /ventas/{id}/ticket
     * Descarga/visualiza el PDF directamente en el navegador.
     */
    public function generar(Request $request, int $ventaId)
    {
        $venta = Venta::findOrFail($ventaId);
        $esReimpresion = $request->boolean('reimpresion');
        $pdf = $this->construirPdf($venta, $esReimpresion);
        $numeroTicketCompleto = $this->numeroTicketCompleto($venta);

        return $pdf->stream("ticket-{$numeroTicketCompleto}.pdf");
    }

    /**
     * POST /ventas/{id}/ticket/email
     * Genera el mismo PDF y lo envía por correo a la dirección indicada.
     */
    public function enviarPorEmail(Request $request, int $ventaId)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $venta = Venta::findOrFail($ventaId);
        $pdf = $this->construirPdf($venta);
        $numeroTicketCompleto = $this->numeroTicketCompleto($venta);

        Mail::to($data['email'])->send(
            new TicketVentaMail($venta, $pdf->output(), $numeroTicketCompleto)
        );

        return response()->json(['message' => 'Ticket enviado correctamente.']);
    }
}
