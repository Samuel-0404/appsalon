<?php
// Servicio de Notificaciones por Email - Entrega Final (Módulo 6)

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

        // 3. Envío directo a Mailtrap vía SMTP
        self::enviarSmtp($destinatarioEmail, $asunto, $cuerpoHTML);

        return true;
    }

    /**
     * Envía correo vía socket SMTP directo (Mailtrap) sin requerir librerías externas
     */
    public static function enviarSmtp(string $para, string $asunto, string $html): bool {
        if (!defined('SMTP_HOST') || empty(SMTP_HOST) || !defined('SMTP_USER') || empty(SMTP_USER) || !defined('SMTP_PASS') || empty(SMTP_PASS)) {
            return false;
        }

        $host = SMTP_HOST;
        $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 2525;
        $user = SMTP_USER;
        $pass = SMTP_PASS;
        $from = defined('MAIL_FROM') ? MAIL_FROM : 'citas@appsalon.com';
        $fromName = defined('MAIL_NAME') ? MAIL_NAME : 'AppSalon Citas';

        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            error_log("No se pudo conectar a Mailtrap SMTP ($host:$port): $errstr ($errno)");
            return false;
        }

        stream_set_timeout($socket, 5);

        $leer = function($sock, $esperado = '250') {
            $respuesta = '';
            while ($linea = fgets($sock, 512)) {
                $respuesta .= $linea;
                if (strlen($linea) >= 4 && substr($linea, 3, 1) === ' ') {
                    break;
                }
            }
            return (empty($esperado) || str_starts_with($respuesta, (string)$esperado));
        };

        $enviar = function($sock, $cmd) {
            fputs($sock, $cmd . "\r\n");
        };

        if (!$leer($socket, '220')) { fclose($socket); return false; }

        $enviar($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$leer($socket, '250')) { fclose($socket); return false; }

        $enviar($socket, "AUTH LOGIN");
        if (!$leer($socket, '334')) { fclose($socket); return false; }

        $enviar($socket, base64_encode($user));
        if (!$leer($socket, '334')) { fclose($socket); return false; }

        $enviar($socket, base64_encode($pass));
        if (!$leer($socket, '235')) { fclose($socket); return false; }

        $enviar($socket, "MAIL FROM: <$from>");
        if (!$leer($socket, '250')) { fclose($socket); return false; }

        $enviar($socket, "RCPT TO: <$para>");
        if (!$leer($socket, '250')) { fclose($socket); return false; }

        $enviar($socket, "DATA");
        if (!$leer($socket, '354')) { fclose($socket); return false; }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $from . ">\r\n";
        $headers .= "To: <" . $para . ">\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
        $headers .= "Date: " . date(DATE_RFC2822) . "\r\n";
        $headers .= "\r\n";

        $enviar($socket, $headers . $html . "\r\n.");
        if (!$leer($socket, '250')) { fclose($socket); return false; }

        $enviar($socket, "QUIT");
        fclose($socket);

        return true;
    }

    /**
     * Guarda una copia HTML del correo en docs/correos_enviados/ para evidencia en la sustentación
     */
    private static function guardarBandejaLocal(string $para, string $asunto, string $html, int $citaId): void {
        $dir = __DIR__ . '/../docs/correos_enviados';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $nombreArchivo = $dir . '/cita_' . $citaId . '_' . date('Ymd_His') . '.html';
        @file_put_contents($nombreArchivo, $html);
    }
}