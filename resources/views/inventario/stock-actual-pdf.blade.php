<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }

        .header {
            background: #2A9D7F;
            color: #fff;
            padding: 16px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .header p {
            margin: 4px 0 0;
            font-size: 11px;
            opacity: .9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th {
            background: #f2f2f2;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
        }

        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
        }

        .bajo {
            color: #b45309;
            font-weight: bold;
        }

        .agotado {
            color: #dc2626;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Inventario actual — {{ $sucursalNombre }}</h1>
        <p>Generado el {{ $fechaGeneracion }}@if ($search)
                · Filtro: "{{ $search }}"
            @endif
        </p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Variante</th>
                <th>SKU</th>
                <th>Cantidad</th>
                <th>Mínimo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r->producto_nombre }}</td>
                    <td>{{ $r->variante_nombre }}</td>
                    <td>{{ $r->sku ?? '—' }}</td>
                    <td class="{{ $r->cantidad <= 0 ? 'agotado' : ($r->bajo_stock ? 'bajo' : '') }}">
                        {{ $r->cantidad }}
                    </td>
                    <td>{{ $r->cantidad_minima }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
