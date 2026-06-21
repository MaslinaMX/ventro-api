<!DOCTYPE html>
<html>

<body style="font-family: sans-serif; padding: 32px; color: #333;">
    <h2>Hola, {{ $user->first_name }}</h2>
    <p>Alguien solicitó restablecer la contraseña de tu cuenta en Ventro.</p>
    <p>
        <a href="{{ $url }}"
            style="background:#000;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;">
            Restablecer contraseña
        </a>
    </p>
    <p style="color:#999;font-size:12px;">
        Este enlace expira en 60 minutos. Si no solicitaste esto, ignora este correo.
    </p>
</body>

</html>
