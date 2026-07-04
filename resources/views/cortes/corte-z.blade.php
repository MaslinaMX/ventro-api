<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 30px 35px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 0;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 18px;
            margin: 0 0 2px 0;
        }

        .header .subtitulo {
            font-size: 13px;
            font-weight: bold;
            color: #444;
            margin: 4px 0;
        }

        .meta {
            font-size: 10px;
            color: #555;
        }

        .seccion {
            margin-bottom: 16px;
        }

        .seccion-titulo {
            font-size: 12px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 4px 8px;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table.resumen td {
            padding: 3px 8px;
            font-size: 11px;
        }

        table.resumen td.label {
            color: #555;
        }

        table.resumen td.valor {
            text-align: right;
            font-weight: bold;
        }

        table.ventas {
            font-size: 9.5px;
        }

        table.ventas th {
            background: #f0f0f0;
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #ccc;
        }

        table.ventas td {
            padding: 3px 6px;
            border-bottom: 1px solid #eee;
        }

        table.ventas td.num {
            text-align: right;
        }

        .total-row td {
            font-weight: bold;
            border-top: 1.5px solid #1a1a1a;
            padding-top: 5px;
        }

        .status-box {
            margin-top: 10px;
            padding: 14px;
            border: 2px solid #1a1a1a;
            text-align: center;
        }

        .status-box .status-label {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-exacto {
            color: #1a7d3a;
        }

        .status-sobrante {
            color: #1565c0;
        }

        .status-faltante {
            color: #c62828;
        }

        .firma {
            margin-top: 50px;
            text-align: center;
        }

        .firma .linea {
            border-top: 1px solid #1a1a1a;
            width: 280px;
            margin: 0 auto 6px auto;
        }

        .firma .nombre {
            font-size: 11px;
            font-weight: bold;
        }

        .firma .rol {
            font-size: 9px;
            color: #666;
        }

        .canceladas {
            margin-top: 10px;
            font-size: 9.5px;
            color: #888;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>{{ $nombreNegocio }}</h1>
        <div class="subtitulo">CORTE DE CAJA Z — CIERRE DE SESIÓN</div>
        <div class="meta">
            {{ $sucursalNombre }} &middot; {{ $cajaNombre }}<br>
            Generado el {{ $generadoEn }} por {{ $generadoPor }}<br>
            Sesión abierta por {{ $abiertaPor }}
        </div>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">RESUMEN DE EFECTIVO</div>
        <table class="resumen">
            <tr>
                <td class="label">Efectivo inicial</td>
                <td class="valor">${{ number_format($montoInicial, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Ventas en efectivo</td>
                <td class="valor">${{ number_format($efectivoVentasCompletadas, 2) }}</td>
            </tr>
            @if (($efectivoCanceladas ?? 0) > 0)
                <tr>
                    <td class="label">Efectivo de ventas canceladas</td>
                    <td class="valor">${{ number_format($efectivoCanceladas, 2) }}</td>
                </tr>
            @endif
            @if (($efectivoDevuelto ?? 0) > 0)
                <tr>
                    <td class="label">Devoluciones en efectivo</td>
                    <td class="valor">-${{ number_format($efectivoDevuelto, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td class="label"><strong>Efectivo esperado</strong></td>
                <td class="valor"><strong>${{ number_format($efectivoEsperado, 2) }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">RESUMEN DE VENTAS</div>
        <table class="resumen">
            @foreach ($totalesPorMetodo as $metodo)
                <tr>
                    <td class="label">{{ $metodo['metodo'] }}</td>
                    <td class="valor">${{ number_format($metodo['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td class="label">Total vendido ({{ $cantidadVentas }}
                    {{ $cantidadVentas === 1 ? 'venta' : 'ventas' }})</td>
                <td class="valor">${{ number_format($totalVentas, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="seccion">
        <div class="seccion-titulo">LISTADO DE VENTAS</div>
        <table class="ventas">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Hora</th>
                    <th>Cajero</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ventasListado ?? [] as $venta)
                    <tr>
                        <td>{{ $venta['numero_ticket_completo'] ?? $venta['numero_ticket'] }}</td>
                        <td>{{ $venta['hora'] ?? '' }}</td>
                        <td>{{ $venta['cajero'] ?? '' }}</td>
                        <td>{{ collect($venta['metodos_pago'] ?? [])->pluck('nombre')->join(', ') }}</td>
                        <td>{{ collect($venta['metodos_pago'] ?? [])->pluck('referencia')->filter()->join(', ') ?:'—' }}
                        </td>
                        <td class="num">${{ number_format($venta['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (!empty($ventasCanceladas))
            <div class="seccion">
                <div class="seccion-titulo">VENTAS CANCELADAS</div>
                <table class="ventas">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Cancelada</th>
                            <th>Devolución</th>
                            <th>Método</th>
                            <th style="text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ventasCanceladas as $vc)
                            <tr>
                                <td>{{ $vc['numero_ticket'] }}</td>
                                <td>{{ $vc['cancelada_en'] ?? '—' }}</td>
                                <td>
                                    @if ($vc['devolucion'])
                                        ${{ number_format($vc['devolucion']['monto_devuelto'], 2) }}
                                        @if ($vc['devolucion']['motivo'])
                                            <br><span
                                                style="color:#888;font-size:8.5px;">{{ $vc['devolucion']['motivo'] }}</span>
                                        @endif
                                    @else
                                        Sin devolución
                                    @endif
                                </td>
                                <td>{{ $vc['devolucion']['metodo'] ?? '—' }}</td>
                                <td class="num">${{ number_format($vc['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="status-box">
        <table class="resumen" style="margin-bottom: 8px;">
            <tr>
                <td class="label">Efectivo esperado</td>
                <td class="valor">${{ number_format($efectivoEsperado, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Efectivo contado</td>
                <td class="valor">${{ number_format($efectivoContado, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Diferencia</td>
                <td class="valor">${{ number_format($diferencia, 2) }}</td>
            </tr>
        </table>
        <div class="status-label status-{{ $status }}">
            @if ($status === 'exacto')
                EXACTO — SIN DIFERENCIAS
            @elseif ($status === 'sobrante')
                SOBRANTE
            @else
                FALTANTE
            @endif
        </div>
    </div>

    <div class="firma">
        <div class="linea"></div>
        <div class="nombre">{{ $generadoPor }}</div>
        <div class="rol">Responsable del corte</div>
    </div>

</body>

</html>
