<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: -apple-system, sans-serif;
            background: #f4f4f5;
            margin: 0;
            padding: 40px 0;
        }

        .card {
            background: #ffffff;
            max-width: 520px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
        }

        .header {
            background: #18181b;
            padding: 32px;
            text-align: center;
        }

        .header h1 {
            color: #ffffff;
            font-size: 22px;
            margin: 0;
            font-weight: 700;
        }

        .header span {
            color: #7c3aed;
        }

        .body {
            padding: 36px 32px;
        }

        .body p {
            color: #52525b;
            font-size: 15px;
            line-height: 1.6;
            margin: 0 0 16px;
        }

        .body strong {
            color: #18181b;
        }

        .btn {
            display: block;
            background: #7c3aed;
            color: #ffffff !important;
            text-decoration: none;
            text-align: center;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            margin: 28px 0;
        }

        .footer {
            padding: 0 32px 32px;
        }

        .footer p {
            color: #a1a1aa;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
        }

        .footer code {
            background: #f4f4f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <h1><span>Ventro</span> POS</h1>
        </div>
        <div class="body">
            <p>Hola, <strong>{{ $user->first_name }}</strong> 👋</p>
            <p>
                Has sido invitado a unirte a <strong>{{ $user->sucursal?->nombre ?? 'Ventro POS' }}</strong>.
                Para activar tu cuenta y establecer tu contraseña, haz clic en el botón:
            </p>
            <a href="{{ $activationUrl }}" class="btn">Activar mi cuenta</a>
            <p>Este enlace expira en <strong>72 horas</strong>.</p>
        </div>
        <div class="footer">
            <p>Si no esperabas esta invitación, puedes ignorar este correo.</p>
            <p>O copia este link en tu navegador:<br><code>{{ $activationUrl }}</code></p>
        </div>
    </div>
</body>

</html>
