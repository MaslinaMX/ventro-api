<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            color: #000;
            width: 80mm;
            margin: 0 auto;
            padding: 12px 10px;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .subtitle {
            font-size: 9px;
            color: #444;
        }

        .section-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: 10px 0 4px;
            text-transform: uppercase;
        }

        .dashed {
            border-top: 1px dashed #999;
            margin: 8px 0;
        }

        .solid {
            border-top: 1px solid #000;
            margin: 8px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 2px 0;
            vertical-align: top;
        }

        .muted {
            color: #555;
        }

        .strike {
            text-decoration: line-through;
            color: #555;
        }

        .venta-box {
            border: 1px solid #999;
            border-radius: 3px;
            padding: 6px 8px;
            margin-bottom: 8px;
        }

        .venta-box.parcial {
            border-style: dashed;
        }

        .tag {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .indent {
            padding-left: 6px;
        }
    </style>
</head>

<body>

    <div class="center">
        <div class="title">CORTE {{ $tipo }}</div>
        <div class="bold" style="font-size: 11px; margin-top: 2px;">{{ strtoupper($nombreNegocio) }}</div>
        <div class="subtitle">{{ $cajaNombre }} · {{ $sucursalNombre }}</div>
    </div>

    <div class="dashed"></div>

    <table>
        <tr>
            <td class="muted">Abierta por</td>
            <td class="right">{{ $abiertaPor }}</td>
        </tr>
        <tr>
            <td class="muted">Generado por</td>
            <td class="right">{{ $generadoPor }}</td>
        </tr>
        <tr>
            <td class="muted">Fecha</td>
            <td class="right">{{ $generadoEn }}</td>
        </tr>
    </table>

    <div class="dashed"></div>

    <div class="section-label">Efectivo</div>
    <table>
        <tr>
            <td class="muted">Monto inicial</td>
            <td class="right">${{ number_format($montoInicial, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">Ventas en efectivo</td>
            <td class="right">${{ number_format($efectivoVentas, 2) }}</td>
        </tr>
    </table>

    <div class="solid"></div>

    <table>
        <tr class="bold">
            <td>EFECTIVO ESPERADO</td>
            <td class="right">${{ number_format($efectivoEsperado, 2) }}</td>
        </tr>
    </table>

    <div class="dashed"></div>

    <div class="section-label">Totales por método de pago</div>
    <table>
        @foreach ($totalesPorMetodo as $m)
            <tr>
                <td>{{ strtoupper($m['metodo']) }}</td>
                <td class="right">${{ number_format($m['total'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="solid"></div>

    <table>
        <tr class="bold">
            <td>TOTAL VENDIDO</td>
            <td class="right">${{ number_format($totalVentas, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" class="muted">{{ $cantidadVentas }} venta(s)</td>
        </tr>
    </table>

    @if (count($ventasCanceladas) > 0)
        <div class="dashed"></div>
        <div class="section-label">Ventas canceladas ({{ count($ventasCanceladas) }})</div>

        @foreach ($ventasCanceladas as $v)
            @php
                $dev = $v['devolucion'] ?? null;
                $esTotal = !$dev || $dev['monto_devuelto'] >= $v['total'] - 0.01;
                $conservado = $dev ? $v['total'] - $dev['monto_devuelto'] : 0;
            @endphp

            @if ($esTotal)
                <div class="venta-box">
                    <table>
                        <tr>
                            <td class="strike">Ticket #{{ $v['numero_ticket'] }}</td>
                            <td class="right strike">${{ number_format($v['total'], 2) }}</td>
                        </tr>
                    </table>
                    @if ($dev)
                        <div class="muted indent">Devuelto: ${{ number_format($dev['monto_devuelto'], 2) }} en
                            {{ $dev['metodo'] }}</div>
                    @endif
                    @if ($dev && !empty($dev['motivo']))
                        <div class="muted indent">Motivo: {{ $dev['motivo'] }}</div>
                    @endif
                </div>
            @else
                <div class="venta-box parcial">
                    <table>
                        <tr>
                            <td class="bold">Ticket #{{ $v['numero_ticket'] }}</td>
                            <td class="right tag">Devolución parcial</td>
                        </tr>
                    </table>
                    <table class="indent">
                        <tr>
                            <td class="muted">Vendido</td>
                            <td class="right">${{ number_format($v['total'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Devuelto en {{ $dev['metodo'] }}</td>
                            <td class="right">- ${{ number_format($dev['monto_devuelto'], 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <div class="dashed" style="margin: 3px 0;"></div>
                            </td>
                        </tr>
                        <tr class="bold">
                            <td>Conservado como venta</td>
                            <td class="right">${{ number_format($conservado, 2) }}</td>
                        </tr>
                    </table>
                    @if (!empty($dev['motivo']))
                        <div class="muted indent" style="margin-top: 4px;">Motivo: {{ $dev['motivo'] }}</div>
                    @endif
                </div>
            @endif
        @endforeach
    @endif

</body>

</html>
