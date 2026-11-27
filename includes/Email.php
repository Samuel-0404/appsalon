<?php
// Servicio de Notificaciones por Email - Entrega Final (Módulo 6)
// Envío real con PHPMailer (composer require phpmailer/phpmailer) por SMTP autenticado con TLS.
// Proveedor recomendado: Brevo (smtp-relay.brevo.com:587, STARTTLS). La configuración vive en config/config.php.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Email {

    /**
     * Envía la confirmación de cita al cliente y guarda copia en bandeja de evidencia
     */
    public static function enviarConfirmacionCita(string $destinatarioEmail, string $nombreCliente, string $fecha, string $hora, array $serviciosNombres, float $total, int $citaId): bool {
        
        $fechaFormateada = date('d/m/Y', strtotime($fecha));
        $horaNum = (int)substr($hora, 0, 2);
        $horaFormateada = ($horaNum < 12) ? $hora.' AM' : (($horaNum === 12) ? '12:00 PM' : ($horaNum - 12).':00 PM');
        $asunto = "Confirmación de tu Cita #" . $citaId . " - AppSalon";

        // 1. Plantilla HTML profesional del correo
        $cuerpoHTML = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 20px; }
                .email-container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
                .email-header { background: #1e1e2d; color: #ffffff; padding: 24px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 24px; color: #818cf8; }
                .email-body { padding: 30px; }
                .cita-card { background: #f1f5f9; border-radius: 8px; padding: 18px; margin: 20px 0; border-left: 4px solid #6366f1; }
                .service-badge { display: inline-block; background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 4px; font-size: 13px; margin: 2px; }
                .email-footer { background: #f8fafc; padding: 18px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>AppSalon</h1>
                    <p style="margin: 4px 0 0 0; font-size: 14px; color: #cbd5e1;">Tu belleza en las mejores manos</p>
                </div>
                <div class="email-body">
                    <h2 style="color: #0f172a; margin-top: 0;">¡Hola, ' . htmlspecialchars($nombreCliente) . '! 👋</h2>
                    <p>Tu cita ha sido agendada con éxito en nuestro sistema. A continuación encontrarás el resumen de tu reserva:</p>
                    
                    <div class="cita-card">
                        <div style="font-size: 13px; color: #64748b; text-transform: uppercase; font-weight: bold;">Cita #' . $citaId . '</div>
                        <div style="font-size: 18px; font-weight: bold; color: #1e293b; margin: 6px 0;">
                            🗓 ' . $fechaFormateada . ' a las ⏱ ' . $horaFormateada . '
                        </div>
                        <div style="margin-top: 12px;">
                            <strong>Servicios reservados:</strong><br>
                            ' . implode(', ', array_map(fn($s) => '<span class="service-badge">' . htmlspecialchars($s) . '</span>', $serviciosNombres)) . '
                        </div>
                        <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #cbd5e1; display: flex; justify-content: space-between;">
                            <span style="font-weight: bold;">Total a pagar:</span>
                            <span style="font-weight: bold; color: #4338ca; font-size: 18px;">$' . number_format($total, 0, ',', '.') . ' COP</span>
                        </div>
                    </div>

                    <p style="font-size: 13px; color: #64748b;">
                        * Te recomendamos llegar 10 minutos antes de tu horario acordado.<br>
                        * Si deseas cancelar o reprogramar, puedes hacerlo desde tu panel de usuario en <strong>Mis Citas</strong>.
                    </p>
                </div>
                <div class="email-footer">
                    &copy; ' . date('Y') . ' AppSalon - Sistema de Gestión de Citas. Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>';

        // 2. Guardar copia en la carpeta de auditoría / evidencia local
        self::guardarBandejaLocal($destinatarioEmail, $asunto, $cuerpoHTML, $citaId);

        // 3. Envío real por SMTP. Devuelve el resultado verdadero (antes siempre era true)
        return self::enviarSmtp($destinatarioEmail, $asunto, $cuerpoHTML, $nombreCliente);
    }

    /**
     * Correo con un enlace de cuenta ('confirmacion' o 'recuperacion').
     * No se guarda copia en el buzón: quien viera el enlace podría tomar la cuenta.
     */
    public static function enviarEnlaceCuenta(string $para, string $nombre, string $tipo, string $url, int $horas): bool {
        [$asunto, $texto, $boton] = $tipo === 'confirmacion'
            ? ['Confirma tu cuenta - AppSalon', 'Para activar tu cuenta, confirma tu correo:', 'Confirmar mi cuenta']
            : ['Recupera tu contraseña - AppSalon', 'Recibimos una solicitud para cambiar tu contraseña:', 'Crear nueva contraseña'];
        $vigencia = $horas === 1 ? '1 hora' : "$horas horas";
        $enlace = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $html = '<div style="font-family: Arial, sans-serif; color: #334155; max-width: 580px; margin: 0 auto;">
            <h2 style="color: #1e1e2d;">AppSalon</h2>
            <p>Hola, ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '.</p>
            <p>' . $texto . '</p>
            <p><a href="' . $enlace . '" style="display: inline-block; background: #4338ca; color: #ffffff; padding: 12px 20px; border-radius: 6px; text-decoration: none;">' . $boton . '</a></p>
            <p style="font-size: 13px;">Si el botón no funciona, copia esta dirección:<br>' . $enlace . '</p>
            <p style="font-size: 13px; color: #64748b;">El enlace vence en ' . $vigencia . '. Si no lo pediste, ignora este mensaje.</p>
        </div>';

        return self::enviarSmtp($para, $asunto, $html, $nombre);
    }

    /**
     * Envía un correo HTML por SMTP autenticado con PHPMailer.
     * Puerto 465 => TLS implícito (SMTPS); cualquier otro (587, 2525) => STARTTLS.
     * Nunca lanza excepciones: si falla, lo registra en el log de PHP y devuelve false,
     * para que una caída del proveedor de correo no impida guardar la cita.
     */
    public static function enviarSmtp(string $para, string $asunto, string $html, string $nombrePara = ''): bool {
        foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'MAIL_FROM'] as $const) {
            if (!defined($const) || trim((string)constant($const)) === '') {
                error_log("[Email] Falta configurar $const: no se envió el correo (solo quedó la copia local).");
                return false;
            }
        }
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            error_log("[Email] Destinatario inválido: $para");
            return false;
        }

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('[Email] Falta vendor/autoload.php: ejecuta "composer install".');
            return false;
        }
        require_once $autoload;

        $puerto = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
        $mail   = new PHPMailer(true);   // true = lanza excepciones en vez de fallar en silencio

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = $puerto;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = $puerto === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout    = 10;      // segundos; evita que la reserva se quede colgada
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            $mail->setFrom(MAIL_FROM, defined('MAIL_NAME') ? MAIL_NAME : 'AppSalon Citas');
            $mail->addAddress($para, $nombrePara);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html;
            $mail->AltBody = self::textoPlano($html);   // versión sin HTML: mejora la entregabilidad

            $mail->send();
            return true;
        } catch (MailerException $ex) {
            error_log("[Email] No se pudo enviar a $para: " . $mail->ErrorInfo);
            return false;
        }
    }

    /** Convierte la plantilla HTML en texto plano para AltBody (sin el bloque <style>). */
    private static function textoPlano(string $html): string {
        $sinEstilos = preg_replace('#<(style|head)\b[^>]*>.*?</\1>#si', '', $html);
        $texto = html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</div>'], "\n", $sinEstilos)), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n\s*\n+/", "\n\n", $texto)));
    }

    /**
     * Guarda una copia HTML del correo en docs/correos_enviados/ para evidencia en la sustentación
     */
    private static function guardarBandejaLocal(string $para, string $asunto, string $html, int $citaId): void {
        $dir = __DIR__ . '/../docs/correos_enviados';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $nombreArchivo = $dir . '/cita_' . $citaId . '_' . date('Ymd_His') . '.html';
        @file_put_contents($nombreArchivo, $html);
    }
}
