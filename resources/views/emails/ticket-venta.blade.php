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
                            <p style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700;">
                                ¡Gracias por tu compra!
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <p
                                style="margin: 0 0 8px; color: #71717a; font-size: 13px; letter-spacing: 0.4px; text-transform: uppercase;">
                                Ticket de compra
                            </p>
                            <p style="margin: 0 0 24px; color: #18181b; font-size: 22px; font-weight: 700;">
                                #{{ $numeroTicketCompleto ?? '' }}
                            </p>

                            <p style="margin: 0 0 24px; color: #52525b; font-size: 15px; line-height: 1.6;">
                                Adjunto encontrarás el comprobante en PDF de tu compra. También puedes consultar el
                                resumen de tu ticket en cualquier momento desde el siguiente enlace:
                            </p>

                            <!-- CTA -->
                            <!-- CTA -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px;">
                                <tr>
                                    <td style="background-color: #2A9D7F; border-radius: 10px;">
                                        <a href="https://app.ventro.com/ticket-digital?ticket={{ $numeroTicketCompleto ?? '' }}"
                                            style="display: inline-block; padding: 14px 24px; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none;">
                                            Ver resumen de mi compra
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #a1a1aa; font-size: 12px; line-height: 1.6;">
                                O copia y pega este enlace en tu navegador:<br>
                                <a href="https://app.ventro.com/ticket-digital?ticket={{ $numeroTicketCompleto ?? '' }}"
                                    style="color: #2A9D7F;">app.ventro.com/ticket-digital?ticket={{ $numeroTicketCompleto ?? '' }}</a>
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
