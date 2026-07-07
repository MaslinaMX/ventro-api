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

        .titulo-cancelacion {
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            border: 1.5px dashed #c0392b;
            color: #c0392b;
            padding: 6px;
            margin: 10px 0;
            border-radius: 4px;
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
            width: 60%;
        }

        .col-importe {
            width: 40%;
            text-align: right;
        }

        .totales td {
            padding: 3px 0;
        }

        .totales .fila-total td {
            font-weight: bold;
            font-size: 12px;
            padding-top: 6px;
            color: #c0392b;
        }

        .info-fila {
            display: block;
            padding: 2px 0;
        }

        .info-label {
            color: #777;
        }

        .badge-inventario {
            font-size: 8px;
            color: #777;
        }

        .motivo-box {
            margin-top: 10px;
            padding: 8px;
            background: #f7f7f7;
            border-radius: 4px;
            font-size: 9px;
            color: #555;
        }

        .barcode {
            margin-top: 16px;
        }

        .barcode img {
            width: 190px;
        }

        .fecha {
            color: #999;
            font-size: 9px;
        }

        .cajero-info {
            margin-top: 18px;
            color: #555;
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

    <div class="titulo-cancelacion">*** COMPROBANTE DE CANCELACIÓN ***</div>

    <div class="dashed"></div>

    <div class="info-fila"><span class="info-label">Ticket original:</span> <strong>{{ $numeroTicketCompleto }}</strong>
    </div>
    <div class="info-fila"><span class="info-label">Fecha de venta:</span> {{ $fechaVentaOriginal }}</div>
    <div class="info-fila"><span class="info-label">Fecha de cancelación:</span> {{ $fechaCancelacion }}</div>
    <div class="info-fila"><span class="info-label">Cancelado por:</span> {{ strtoupper($canceladoPor) }}</div>

    <div class="dashed"></div>

    <table>
        @foreach ($itemsDevueltos as $item)
            <tr>
                <td class="col-nombre">
                    {{ $item['cantidad'] }} x {{ $item['nombre_snapshot'] }}
                    @if ($item['devuelto_a_inventario'])
                        <div class="badge-inventario">↩ regresado a inventario</div>
                    @endif
                </td>
                <td class="col-importe">${{ number_format($item['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="dashed"></div>

    <table class="totales">
        <tr>
            <td>MONTO ORIGINAL</td>
            <td class="right">${{ number_format($montoOriginal, 2) }}</td>
        </tr>
        <tr>
            <td>MÉTODO DE DEVOLUCIÓN</td>
            <td class="right">{{ strtoupper($metodoDevolucion) }}</td>
        </tr>
        <tr class="fila-total">
            <td>MONTO DEVUELTO</td>
            <td class="right">${{ number_format($montoDevuelto, 2) }}</td>
        </tr>
    </table>

    @if ($motivo)
        <div class="motivo-box">
            <strong>Motivo:</strong> {{ $motivo }}
        </div>
    @endif

    <div class="cajero-info">
        <div>Cajero original: {{ strtoupper($cajeroNombre) }}</div>
        <div>Caja: {{ $cajaNombre }}</div>
    </div>

    <div class="center barcode">
        <img src="data:image/png;base64,{{ $barcodeBase64 }}">
        <div class="bold">{{ $numeroTicketCompleto }}</div>
        <div class="fecha">{{ $fechaCancelacion }}</div>
    </div>
</body>

</html>
