<?php
// Plantilla de configuración. Copiarla como config/config.php (que está en .gitignore).
// Cada valor se lee de una variable de entorno y, si no existe, usa el valor por defecto de XAMPP.
//   - XAMPP:  no hay variables de entorno -> se usan los valores por defecto de abajo.
//   - Docker: docker-compose.yml inyecta las variables desde el archivo .env.
// Nunca subas credenciales reales al repositorio: van en config/config.php o en .env.

if (!function_exists('env')) {
    function env(string $clave, string $porDefecto = ''): string {
        $valor = getenv($clave);
        return ($valor === false || $valor === '') ? $porDefecto : $valor;
    }
}

// --- Base de datos ---
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'appsalon_mvc_php'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Ruta pública de la app. XAMPP: '/appsalon/public'. Docker (DocumentRoot = public/): '' (vacío).
// Aquí se usa getenv() directo: en Docker BASE_URL viene definida pero VACÍA, y eso debe respetarse.
define('BASE_URL', rtrim(getenv('BASE_URL') !== false ? getenv('BASE_URL') : '/appsalon/public', '/'));

// --- Correo (SMTP con TLS). Brevo: smtp-relay.brevo.com, puerto 587 (STARTTLS) ---
define('SMTP_HOST', env('SMTP_HOST', 'smtp-relay.brevo.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', '587'));
define('SMTP_USER', env('SMTP_USER'));            // login SMTP de Brevo (xxxx@smtp-brevo.com)
define('SMTP_PASS', env('SMTP_PASS'));            // clave SMTP de Brevo (no la contraseña de la cuenta)
define('MAIL_FROM', env('MAIL_FROM'));            // remitente VERIFICADO en Brevo (p. ej. tu Gmail)
define('MAIL_NAME', env('MAIL_NAME', 'AppSalon Citas'));
