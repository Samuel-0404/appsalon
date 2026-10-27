# AppSalon - Sistema de Gestión de Citas

Aplicación web desarrollada con PHP MVC propio y MySQL para la gestión de un salón de belleza.

---

## 1. Instrucciones de Instalación

1. **Ubicación del proyecto:**
   - Clonar o mover la carpeta del repositorio dentro del directorio `htdocs` de XAMPP:
     ```text
     C:\xampp\htdocs\appsalon
     ```

2. **Iniciar servicios:**
   - Abrir el panel de control de **XAMPP** e iniciar los servicios de **Apache** y **MySQL**.

3. **Importar la Base de Datos:**
   - Ingresar a phpMyAdmin en el navegador: `http://localhost/phpmyadmin/`.
   - Ir a la pestaña **Importar** (o pestaña **SQL**) y seleccionar el script del proyecto:
     ```text
     database/appsalon.sql
     ```
   - Al ejecutarlo, se creará automáticamente la base de datos `appsalon_mvc_php`, las 4 tablas con claves foráneas y los datos de prueba.

4. **Configuración del entorno:**
   - Asegurarse de tener el archivo de configuración `includes/config.php` (si no existe, copiar `config/config.example.php` a `includes/config.php`).
   - Configuración predeterminada para XAMPP:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'appsalon_mvc_php');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('BASE_URL', '/appsalon/public');
     ```

5. **Acceso a la aplicación:**
   - Abrir en el navegador:
     ```text
     http://localhost/appsalon/public/
     ```

---

## 2. Credenciales de Usuarios de Prueba

| Rol | Correo Electrónico | Contraseña | Redirección Inicial |
|:---|:---|:---|:---|
| **Administrador** | `admin@appsalon.com` | `admin123` | Panel de Administración de Servicios (`servicios/admin`) |
| **Cliente** | `cliente@appsalon.com` | `cliente123` | Catálogo Público de Servicios (`servicios/index`) |

---

## 3. Descripción de Funcionalidades Implementadas

### Módulo de Autenticación y Seguridad
- **Registro de usuarios:** Validación en servidor de nombre y apellido (solo letras), email único y válido, teléfono de 10 dígitos y contraseña mínima de 6 caracteres con confirmación.
- **Cifrado de contraseñas:** Uso de hash seguro con `password_hash()` mediante algoritmo **bcrypt** y verificación con `password_verify()`.
- **Inicio y cierre de sesión:** Autenticación con regeneración de ID de sesión (`session_regenerate_id()`) para evitar fijación de sesiones, destrucción de cookies en logout y redirección inteligente por rol.
- **Control de accesos y roles:** Protección de rutas mediante guards en servidor (`requireLogin()` y `requireAdmin()`).
- **Seguridad CSRF:** Generación y validación estricta de tokens en peticiones POST (`validarCsrf()`).
- **Seguridad XSS:** Sanitización sistemática de salidas mediante la función de escape `e()` (`htmlspecialchars`).

### Módulo de Gestión de Servicios
- **Catálogo público:** Visualización de servicios en tarjetas responsivas de Bootstrap con duración, descripción y precio formateado en Pesos Colombianos (COP).
- **CRUD para administradores:**
  - **Listar:** Tabla administrativa con el catálogo completo y botones de acción.
  - **Crear:** Formulario con persistencia de datos ante fallos y validación de nombre obligatorio y precio numérico mayor a cero.
  - **Editar:** Formulario con precarga de información y validación del lado del servidor.
  - **Eliminar:** Petición POST protegida con token CSRF y mensaje de confirmación antes de borrar el registro.
- **Validación visual:** Marcado de campos erróneos con la clase `is-invalid` y mensajes `.invalid-feedback` de Bootstrap.
- **Mensajes de estado:** Sistema de alertas flash dismissibles para notificar el éxito o fracaso de las operaciones.

---

## 4. Tecnologías Utilizadas

- **Backend:** PHP 8+ (Arquitectura MVC propia con Front Controller en `public/index.php`).
- **Base de Datos:** MySQL / MariaDB interactuando mediante **PDO** y **Prepared Statements** en todas las consultas.
- **Frontend:** HTML5 semántico, CSS3, JavaScript Vanilla y **Bootstrap 5.3.3**.
- **Tipografía:** Google Fonts (*Poppins*).
- **Entorno de Ejecución:** Servidor local XAMPP (Apache + MySQL / phpMyAdmin).
