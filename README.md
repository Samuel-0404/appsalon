# AppSalon - Sistema de Gestión de Citas

Aplicación web desarrollada con PHP MVC propio y MariaDB/MySQL para la gestión de un salón de belleza:
reservas con calendario, panel del salón por roles, API REST, reportes y despliegue con Docker.

---

## 1. Instalación

Hay dos formas de correr el proyecto. **Docker** es la recomendada (es la misma configuración del servidor).

### Opción A — Docker (recomendada)

Requisitos: Docker Engine con el plugin `compose`.

```bash
git clone -b main https://github.com/Samuel-0404/appsalon.git
cd appsalon
cp .env.example .env          # completar claves de BD y de Brevo (formato CLAVE=valor)
docker compose up -d --build
```

Abrir http://localhost:8080. La base de datos se crea e importa sola la primera vez
(`appsalon.sql` → migraciones `002` a `005` → `datos_prueba.sql`).

Despliegue en un servidor con HTTPS (Caddy + Let's Encrypt): ver [`docs/DESPLIEGUE.md`](docs/DESPLIEGUE.md).

### Opción B — XAMPP

1. Copiar el proyecto en `htdocs` (Linux: `/opt/lampp/htdocs/appsalon`, Windows: `C:\xampp\htdocs\appsalon`) e iniciar **Apache** y **MySQL**.
2. Importar `database/appsalon.sql` en phpMyAdmin (crea la BD `appsalon_mvc_php`, todas las tablas y los usuarios de prueba).
   Opcional: importar `database/datos_prueba.sql` (10 clientes y 32 citas para los reportes).
3. Copiar `config/config.example.php` como `config/config.php`. Con XAMPP no hay que cambiar nada de la BD;
   para el correo, completar `SMTP_USER`, `SMTP_PASS` y `MAIL_FROM` (Brevo).
4. Instalar dependencias en la raíz del proyecto: `composer install` (TCPDF y PHPMailer en `vendor/`).
5. Abrir http://localhost/appsalon/public/

**¿Ya tenías la BD de una entrega anterior?** No hace falta reimportar: aplica las migraciones que te falten
(todas son idempotentes, se pueden ejecutar más de una vez):

```bash
/opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/002_api_reportes.sql
/opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/003_festivos.sql
/opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/004_roles_horario.sql
/opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/005_cuentas.sql
```

Con Docker y una base ya creada:

```bash
docker compose up -d --build
docker compose exec db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" appsalon_mvc_php < /docker-entrypoint-initdb.d/05_cuentas.sql'
```

En XAMPP, si tu `config/config.php` es anterior, agrega `APP_URL` como en `config.example.php`.

---

## 2. Usuarios de prueba

| Rol | Correo | Contraseña | Al iniciar sesión |
|:---|:---|:---|:---|
| **Administrador** | `admin@appsalon.com` | `admin123` | Panel del salón (`admin/dashboard`) |
| **Empleado** | `empleado@appsalon.com` | `empleado123` | Panel del salón (agenda y disponibilidad) |
| **Cliente** | `cliente@appsalon.com` | `cliente123` | Catálogo de servicios (`servicios/index`) |

`datos_prueba.sql` agrega 10 clientes `@example.com` (contraseña `cliente123`).

---

## 3. Funcionalidades

### Cuentas
- **Confirmación por correo:** la cuenta nueva no puede ingresar hasta abrir el enlace (vence en 24 horas, se puede
  reenviar). El administrador también puede confirmarla desde Usuarios.
- **Recuperación de contraseña:** enlace de un solo uso que vence en 1 hora. La respuesta no revela si el correo existe.
- Ambos requieren SMTP configurado.

### Sistema de citas
- **Calendario interactivo** (Flatpickr) con los días cerrados y los **festivos de Colombia** bloqueados.
- **Selección múltiple de servicios** con total, duración y resumen en vivo.
- **Disponibilidad real por duración:** una cita ocupa `[hora, hora + duración de sus servicios)`. El calendario
  solo ofrece las horas donde la duración elegida cabe antes del cierre y sin cruzarse con otra cita o bloqueo.
- **Reglas únicas en `models/ValidadorCita.php`**, usadas por la web y por la API: fecha no pasada, día abierto
  según el horario, no festivo, servicios activos, fin antes del cierre y sin solapamiento. La reserva se
  serializa por fecha con `GET_LOCK` para evitar dobles reservas simultáneas.
- **Cancelar y reprogramar** citas pendientes propias.
- **Correo de confirmación** con fecha, hora, servicios y total, enviado con PHPMailer por SMTP con TLS (Brevo)
  al correo con el que se registró el cliente. Se guarda copia HTML en `docs/correos_enviados/`.

### Festivos de Colombia (consumo de API externa)
- `models/Festivo.php` consume la API pública **Nager.Date** con cURL (timeouts, validación de status y JSON) y
  guarda los festivos en la tabla `festivos` (carga perezosa por año). Las validaciones siempre leen la BD,
  así que si la API externa se cae el sistema sigue validando.
- Endpoint interno `citas/festivos&anio=AAAA` (JSON) que consume el calendario.

### Panel del salón (roles)
| Función | Empleado | Administrador |
|---|:---:|:---:|
| Dashboard: citas de hoy, ingresos del mes, pendientes y total de clientes | ✅ | ✅ |
| Agenda **por día o por semana** con filtro de estado y navegación | ✅ | ✅ |
| Búsqueda de citas por cliente, email, teléfono o número, en todas las fechas | ✅ | ✅ |
| Confirmar / completar / cancelar citas | ✅ | ✅ |
| Disponibilidad: bloquear y liberar franjas horarias | ✅ | ✅ |
| **Horario del salón** configurable por día de la semana (`admin/horario`) | | ✅ |
| Usuarios: buscar, filtrar, confirmar, activar/desactivar y **asignar rol** | | ✅ |
| Servicios (CRUD), reportes y buzón de correos | | ✅ |

- El rol nunca se toma de un formulario público: todo registro nace como `cliente` y solo un administrador lo
  cambia. Un administrador no puede cambiar su propio rol ni desactivarse.
- Al cambiar un rol se revocan los tokens de API del usuario.

### API REST
- Front controller en `public/api.php` con rutas limpias `/api/...` (`public/.htaccess`), JSON con sobre
  uniforme y códigos HTTP 200/201/204/400/401/403/404/405/409/415/422.
- Autenticación con token Bearer de 256 bits (solo se guarda su hash SHA-256; vence a las 8 h).
- Todas las rutas requieren token, excepto `POST /auth/register` y `POST /auth/login`.
- Endpoints: `POST /auth/register|login|logout|logout-all`, `GET|PUT /auth/profile`, `GET /tokens`,
  `DELETE /tokens/{id}`, `GET /servicios`, `GET /servicios/{id}`, `GET /citas/usuario/{id}`, `GET /citas/{id}`,
  `POST /citas` y `PUT /citas/{id}`.
- **Swagger UI** (documentación interactiva): http://localhost:8080/swagger/ con Docker, o
  http://localhost/appsalon/public/swagger/ con XAMPP. Requiere internet para cargar la interfaz.
- Documentación en [`docs/API.md`](docs/API.md) y colección de Postman en `docs/AppSalon_API.postman_collection.json`.

### Reportes y exportación
- Filtros por **período, estado y servicio**; KPIs (ingresos realizados, por cobrar, ticket promedio, tasa de
  cancelación) y gráficas con **Chart.js**. Con filtro de servicio, los ingresos usan el precio de ese servicio.
- Exportación a **CSV** (citas, servicios y serie diaria; BOM UTF-8, separador `;`, protección contra inyección
  de fórmulas) y a **PDF** (TCPDF).

### Seguridad
- Contraseñas con `password_hash()` (bcrypt), regeneración del ID de sesión, cookies `HttpOnly` + `SameSite`.
- CSRF en todos los POST (`validarCsrf()`), escape de salida con `e()` y *prepared statements* en todas las consultas.
- Guards por rol en el servidor: `requireLogin()`, `requirePersonal()` y `requireAdmin()`.
- Los servicios se desactivan en lugar de borrarse, para conservar el historial de citas y los reportes.
- Secretos fuera del repositorio: `config/config.php` y `.env` están en `.gitignore`.

---

## 4. Estructura

```text
config/        configuración (config.example.php lee variables de entorno) y conexión PDO
controllers/   Auth, Service, Appointment, Admin, Report y Api
models/        User, Cuenta, Service, Appointment, ValidadorCita, Horario, Festivo, Report, ApiToken
views/         vistas por módulo + layout (Bootstrap 5)
public/        front controllers (index.php y api.php), .htaccess, CSS, JS y swagger/
database/      appsalon.sql (esquema completo), migraciones/ y datos_prueba.sql
docker/        configuración de Apache, PHP y Caddy
docs/          API.md, DESPLIEGUE.md y colección de Postman
```

---

## 5. Tecnologías

- **Backend:** PHP 8.3 (MVC propio con front controller), PDO + prepared statements.
- **Base de datos:** MariaDB 11 / MySQL (XAMPP).
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5.3, Flatpickr y Chart.js.
- **Librerías (Composer):** TCPDF (PDF) y PHPMailer (correo SMTP).
- **Servicios externos:** Nager.Date (festivos) y Brevo (SMTP).
- **Infraestructura:** Docker + Docker Compose, Caddy (HTTPS) y Microsoft Azure (VM Ubuntu).
