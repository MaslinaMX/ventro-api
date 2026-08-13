<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            width: 80mm;
            margin: 0 auto;
            padding: 16px 12px;
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

        .logo {
            max-height: 48px;
            margin-bottom: 10px;
        }

        .nombre-negocio {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.8px;
        }

        .datos-negocio {
            color: #555;
            line-height: 1.5;
        }

        .dashed {
            border-top: 1px dashed #ccc;
            margin: 12px 0;
        }

        .dashed.suave {
            border-top-color: #e8e8e8;
        }

        .cliente-info {
            text-align: center;
            color: #555;
            margin-top: 10px;
            font-size: 10px;
        }

        .cliente-info .label {
            color: #999;
            font-size: 9px;
            letter-spacing: 0.4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Courier New', monospace;
        }

        td {
            padding: 3px 0;
            vertical-align: top;
        }

        .col-nombre {
            width: 50%;
        }

        .col-precio,
        .col-importe {
            width: 25%;
            text-align: right;
        }

        .totales td {
            padding: 3px 0;
        }

        .totales .fila-total td {
            font-weight: bold;
            font-size: 12px;
            padding-top: 6px;
        }

        .totales .fila-descuento td {
            color: #b8860b;
        }

        .ref-row td {
            font-weight: normal;
            font-size: 9px;
            color: #777;
            padding-top: 0;
            padding-bottom: 4px;
        }

        .barcode {
            margin-top: 16px;
        }

        .barcode img {
            width: 190px;
        }

        .footer-msg {
            margin-top: 12px;
            color: #777;
            font-size: 9px;
            font-style: italic;
        }

        .gracias {
            margin-top: 16px;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.4px;
        }

        .cajero-info {
            margin-top: 18px;
            color: #555;
        }

        .fecha {
            color: #999;
            font-size: 9px;
        }

        .reimpresion {
            text-align: center;
            font-weight: bold;
            border: 1px dashed #999;
            color: #777;
            padding: 5px;
            margin: 10px 0;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="center">
        @if ($mostrarLogo && $logoBase64)
            <img src="{{ $logoBase64 }}" class="logo"><br>
        @endif

        <div class="nombre-negocio">{{ strtoupper($nombreNegocio) }}</div>
        <div class="datos-negocio">
            @if ($direccion)
                <div>{{ strtoupper($direccion) }}</div>
            @endif
            @if ($ciudadEstadoCp)
                <div>{{ strtoupper($ciudadEstadoCp) }}</div>
            @endif
            @if ($rfc)
                <div>RFC: {{ strtoupper($rfc) }}</div>
            @endif
            @if ($telefono)
                <div>TEL: {{ $telefono }}</div>
            @endif
        </div>
    </div>

    <div class="cliente-info">
        <span class="label">CLIENTE</span><br>
        {{ strtoupper($clienteNombre) }}
    </div>

    <div class="dashed"></div>

    <table>
        @foreach ($items as $item)
            <tr>
                <td class="col-nombre">{{ $item['cantidad'] }} x {{ $item['nombre_snapshot'] }}</td>
                <td class="col-precio">${{ number_format($item['precio_unitario'], 2) }}</td>
                <td class="col-importe">${{ number_format($item['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="dashed"></div>

    <table class="totales">
        @if ($descuento > 0)
            <tr>
                <td>TOTAL</td>
                <td class="right">${{ number_format($totalBruto, 2) }}</td>
            </tr>
            <tr class="fila-descuento">
                <td>DESCUENTO</td>
                <td class="right">-${{ number_format($descuento, 2) }}</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>SUBTOTAL</td>
                <td class="right">${{ number_format($baseGravable, 2) }}</td>
            </tr>
        @else
            <tr>
                <td>SUBTOTAL</td>
                <td class="right">${{ number_format($subtotal, 2) }}</td>
            </tr>
        @endif
        @if ($ivaTotal > 0)
            <tr>
                <td>IVA</td>
                <td class="right">${{ number_format($ivaTotal, 2) }}</td>
            </tr>
        @endif
        @if ($iepsTotal > 0)
            <tr>
                <td>IEPS</td>
                <td class="right">${{ number_format($iepsTotal, 2) }}</td>
            </tr>
        @endif
        <tr class="fila-total">
            <td>TOTAL</td>
            <td class="right">${{ number_format($total, 2) }}</td>
        </tr>
    </table>

    <div style="height: 8px;"></div>

    <table class="totales">
        @foreach ($pagos as $pago)
            <tr>
                <td>{{ strtoupper($pago['metodo']) }}</td>
                <td class="right">${{ number_format($pago['monto'], 2) }}</td>
            </tr>
            @if (!empty($pago['referencia']))
                <tr class="ref-row">
                    <td colspan="2">Ref: {{ $pago['referencia'] }}</td>
                </tr>
            @endif
            @if (!empty($pago['recibido']) && $pago['cambio'] > 0)
                <tr>
                    <td>RECIBIDO</td>
                    <td class="right">${{ number_format($pago['recibido'], 2) }}</td>
                </tr>
            @endif
            @if ($pago['cambio'] > 0)
                <tr>
                    <td>CAMBIO</td>
                    <td class="right">${{ number_format($pago['cambio'], 2) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <div class="cajero-info">
        <div>Cajero: {{ strtoupper($cajeroNombre) }}</div>
        <div>Caja: {{ $cajaNombre }}</div>
    </div>

    <div class="center barcode">
        <img src="data:image/png;base64,{{ $barcodeBase64 }}">
        <div class="bold">{{ $numeroTicketCompleto }}</div>
        <div class="fecha">{{ $fechaFormateada }}</div>
    </div>

    @if ($mensajePersonalizado)
        <div class="center footer-msg">{{ $mensajePersonalizado }}</div>
    @endif


    @if ($esReimpresion)
        <div class="reimpresion">
            *** REIMPRESIÓN ***
        </div>
    @endif
</body>

</html>
