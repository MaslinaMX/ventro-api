<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="background-color: #f4f4f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width: 480px; background-color: #ffffff; border-radius: 16px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td style="background-color: #2A9D7F; padding: 32px 32px 28px; text-align: center;">
                            <div
                                style="width: 48px; height: 48px; background-color: rgba(255,255,255,0.15); border-radius: 12px; margin: 0 auto 16px; line-height: 48px; text-align: center;">
                                <span style="font-size: 22px;">🔒</span>
                            </div>
                            <p style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700;">
                                Restablecer contraseña
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 24px; color: #52525b; font-size: 15px; line-height: 1.6;">
                                Hola, <strong style="color: #18181b;">{{ $user->first_name }}</strong> — alguien
                                solicitó
                                restablecer la contraseña de tu cuenta en Ventro. Haz clic en el botón para elegir una
                                nueva:
                            </p>

                            <!-- CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
                                <tr>
                                    <td style="background-color: #2A9D7F; border-radius: 10px;">
                                        <a href="{{ $url }}"
                                            style="display: inline-block; padding: 14px 24px; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #a1a1aa; font-size: 12px; line-height: 1.6;">
                                O copia y pega este enlace en tu navegador:<br>
                                <span style="color: #2A9D7F; word-break: break-all;">{{ $activationUrl }}</span>
                            </p>

                            <p style="margin: 0; color: #a1a1aa; font-size: 12px; line-height: 1.6;">
                                Este enlace expira en <strong style="color: #71717a;">60 minutos</strong>. Si no
                                solicitaste esto, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px; border-top: 1px solid #f0f0f0; text-align: center;">
                            <p style="margin: 0; color: #a1a1aa; font-size: 12px;">
                                Enviado con <a style="text-decoration: none;" href="https://www.ventro.com.mx"><strong
                                        style="color: #71717a;">Ventro</strong></a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
