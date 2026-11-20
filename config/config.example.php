<?php
// Copia local por defecto (XAMPP). Está en .gitignore; en el repo va config/config.example.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'appsalon_mvc_php');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', '/appsalon/public');

// Configuración de Email (PHPMailer / Mailtrap)
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io');
define('SMTP_PORT', 2525);
define('SMTP_USER', '.........');
define('SMTP_PASS', '.........');
define('MAIL_FROM', 'citas@appsalon.com');
define('MAIL_NAME', 'AppSalon Citas');
